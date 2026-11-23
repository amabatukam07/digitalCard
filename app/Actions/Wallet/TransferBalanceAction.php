<?php

namespace App\Actions\Wallet;

use App\Models\AuditLog;
use App\Models\CardTransaction;
use App\Models\User;
use App\Models\VirtualCard;
use App\Notifications\WalletTransferReceivedNotification;
use App\Services\CartPricing;
use App\Services\WalletLedgerService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransferBalanceAction
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    /**
     * Transfere saldo de forma atômica entre dois cartões virtuais usando lock determinístico por ID.
     *
     * @return array{out: CardTransaction, in: CardTransaction, recipient: User}
     */
    public function execute(
        User $sender,
        string $recipientEmail,
        float $amount,
        ?string $note = null,
        ?string $idempotencyKey = null,
    ): array {
        $normalizedEmail = mb_strtolower(trim($recipientEmail));
        $amountCents = CartPricing::toCents($amount);

        if ($amountCents < 100 || $amountCents > 1000000) {
            throw new DomainException('O valor da transferência deve ficar entre R$ 1,00 e R$ 10.000,00.');
        }

        if ($normalizedEmail === mb_strtolower($sender->email)) {
            throw new DomainException('Você não pode transferir saldo para a própria conta.');
        }

        $recipient = User::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();

        if (! $recipient) {
            throw new DomainException('Destinatário não encontrado com o e-mail informado.');
        }

        $senderCard = $this->ensureCard($sender);
        $recipientCard = $this->ensureCard($recipient);

        $transferUid = $idempotencyKey ?: (string) Str::uuid();
        $cleanNote = $note !== null && trim($note) !== '' ? trim($note) : null;

        /** @var array{out: CardTransaction, in: CardTransaction} $transactions */
        $transactions = DB::transaction(function () use (
            $senderCard,
            $recipientCard,
            $sender,
            $recipient,
            $amountCents,
            $cleanNote,
            $transferUid
        ): array {
            $lockedCards = VirtualCard::query()
                ->whereIn('id', [$senderCard->id, $recipientCard->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedSender = $lockedCards->get($senderCard->id);
            $lockedRecipient = $lockedCards->get($recipientCard->id);

            if (! $lockedSender || ! $lockedRecipient) {
                throw new DomainException('Não foi possível localizar as carteiras envolvidas na transferência.');
            }

            if (! $lockedSender->is_active) {
                throw new DomainException('Seu cartão virtual está bloqueado temporariamente. Desbloqueie-o para transferir saldo.');
            }

            if (! $lockedRecipient->is_active) {
                throw new DomainException('A carteira de destino está bloqueada no momento e não pode receber transferências.');
            }

            if (empty($lockedSender->security_pin)) {
                throw new DomainException('Configure um PIN de 4 dígitos no seu cartão virtual antes de realizar transferências.');
            }

            $balanceCents = CartPricing::toCents((float) $lockedSender->balance);
            if ($amountCents > $balanceCents) {
                throw new DomainException('Saldo insuficiente na carteira para concluir a transferência.');
            }

            if ($lockedSender->daily_limit !== null) {
                $limitCents = CartPricing::toCents((float) $lockedSender->daily_limit);
                $spentCents = $this->ledger->dailySpentCents($lockedSender);
                if (($spentCents + $amountCents) > $limitCents) {
                    throw new DomainException('Esta transferência ultrapassa o limite diário configurado no seu cartão.');
                }
            }

            $noteSuffix = $cleanNote ? " — \"{$cleanNote}\"" : '';

            $outTx = $this->ledger->debit(
                lockedCard: $lockedSender,
                amountCents: $amountCents,
                type: CardTransaction::TYPE_TRANSFER_OUT,
                description: "Transferência P2P enviada para {$recipient->name} ({$recipient->email}){$noteSuffix}",
                reference: $recipient,
                idempotencyKey: "p2p-out-{$transferUid}",
            );

            $inTx = $this->ledger->credit(
                lockedCard: $lockedRecipient,
                amountCents: $amountCents,
                type: CardTransaction::TYPE_TRANSFER_IN,
                description: "Transferência P2P recebida de {$sender->name} ({$sender->email}){$noteSuffix}",
                reference: $sender,
                idempotencyKey: "p2p-in-{$transferUid}",
            );

            AuditLog::record(
                'wallet.transfer',
                $lockedSender,
                null,
                [
                    'recipient_id' => $recipient->id,
                    'amount' => CartPricing::toDecimal($amountCents),
                    'transaction_out_id' => $outTx->id,
                    'transaction_in_id' => $inTx->id,
                ],
                $sender->id
            );

            return [
                'out' => $outTx,
                'in' => $inTx,
            ];
        });

        $recipient->notify(new WalletTransferReceivedNotification(
            senderName: $sender->name,
            amount: CartPricing::toDecimal($amountCents),
            transaction: $transactions['in'],
            note: $cleanNote,
        ));

        return [
            'out' => $transactions['out'],
            'in' => $transactions['in'],
            'recipient' => $recipient,
        ];
    }

    private function ensureCard(User $user): VirtualCard
    {
        return $user->virtualCard()->firstOrCreate([], [
            'card_number' => VirtualCard::generateUniqueCardNumber(),
            'cvv' => VirtualCard::generateCvv(),
            'expiration_date' => VirtualCard::DEFAULT_EXPIRATION_DATE,
            'balance' => 0.00,
            'cashback_earned' => 0.00,
            'is_active' => true,
        ]);
    }
}
