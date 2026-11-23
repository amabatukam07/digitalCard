<?php

namespace App\Actions\Wallet;

use App\Models\AuditLog;
use App\Models\CardTransaction;
use App\Models\GiftCard;
use App\Models\User;
use App\Models\VirtualCard;
use App\Services\CartPricing;
use App\Services\WalletLedgerService;
use DomainException;
use Illuminate\Support\Facades\DB;

class PurchaseGiftCardAction
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    public function execute(
        User $purchaser,
        float $amount,
        ?string $recipientName = null,
        ?string $recipientEmail = null,
        ?string $message = null,
    ): GiftCard {
        $amountCents = CartPricing::toCents($amount);

        if ($amountCents < 2500 || $amountCents > 500000) {
            throw new DomainException('O vale-presente deve ter valor entre R$ 25,00 e R$ 5.000,00.');
        }

        $card = $purchaser->virtualCard()->firstOrCreate([], [
            'card_number' => VirtualCard::generateUniqueCardNumber(),
            'cvv' => VirtualCard::generateCvv(),
            'expiration_date' => VirtualCard::DEFAULT_EXPIRATION_DATE,
            'balance' => 0.00,
            'cashback_earned' => 0.00,
            'is_active' => true,
        ]);

        return DB::transaction(function () use ($purchaser, $card, $amountCents, $recipientName, $recipientEmail, $message): GiftCard {
            $lockedCard = VirtualCard::query()
                ->whereKey($card->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedCard->is_active) {
                throw new DomainException('Seu cartão virtual está bloqueado temporariamente. Desbloqueie-o para emitir um vale-presente.');
            }

            if (empty($lockedCard->security_pin)) {
                throw new DomainException('Configure um PIN de 4 dígitos no seu cartão virtual antes de emitir um vale-presente.');
            }

            $balanceCents = CartPricing::toCents((float) $lockedCard->balance);
            if ($amountCents > $balanceCents) {
                throw new DomainException('Saldo insuficiente no cartão virtual para emitir este vale-presente.');
            }

            if ($lockedCard->daily_limit !== null) {
                $limitCents = CartPricing::toCents((float) $lockedCard->daily_limit);
                $spentCents = $this->ledger->dailySpentCents($lockedCard);
                if (($spentCents + $amountCents) > $limitCents) {
                    throw new DomainException('A emissão deste vale-presente excede o limite diário do seu cartão.');
                }
            }

            $decimalAmount = CartPricing::toDecimal($amountCents);
            $giftCard = GiftCard::create([
                'code' => GiftCard::generateUniqueCode(),
                'purchaser_user_id' => $purchaser->id,
                'initial_amount' => $decimalAmount,
                'remaining_amount' => $decimalAmount,
                'recipient_name' => $recipientName !== null && trim($recipientName) !== '' ? trim($recipientName) : null,
                'recipient_email' => $recipientEmail !== null && trim($recipientEmail) !== '' ? mb_strtolower(trim($recipientEmail)) : null,
                'message' => $message !== null && trim($message) !== '' ? trim($message) : null,
                'status' => GiftCard::STATUS_ACTIVE,
                'expires_at' => now()->addMonths(12),
            ]);

            $recipientLabel = $giftCard->recipient_name ? " para {$giftCard->recipient_name}" : '';

            $this->ledger->debit(
                lockedCard: $lockedCard,
                amountCents: $amountCents,
                type: CardTransaction::TYPE_GIFT_CARD_PURCHASE,
                description: "Emissão de Vale-Presente {$giftCard->code}{$recipientLabel}",
                reference: $giftCard,
                idempotencyKey: "giftcard-issue-{$giftCard->id}",
            );

            AuditLog::record(
                'gift_card.purchased',
                $giftCard,
                null,
                [
                    'code' => $giftCard->code,
                    'amount' => $decimalAmount,
                ],
                $purchaser->id
            );

            return $giftCard;
        });
    }
}
