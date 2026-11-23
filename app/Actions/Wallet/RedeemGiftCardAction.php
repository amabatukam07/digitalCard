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

class RedeemGiftCardAction
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    public function execute(User $redeemer, string $rawCode): GiftCard
    {
        $code = strtoupper(trim($rawCode));

        if ($code === '') {
            throw new DomainException('Informe um código de vale-presente válido.');
        }

        $card = $redeemer->virtualCard()->firstOrCreate([], [
            'card_number' => VirtualCard::generateUniqueCardNumber(),
            'cvv' => VirtualCard::generateCvv(),
            'expiration_date' => VirtualCard::DEFAULT_EXPIRATION_DATE,
            'balance' => 0.00,
            'cashback_earned' => 0.00,
            'is_active' => true,
        ]);

        return DB::transaction(function () use ($redeemer, $card, $code): GiftCard {
            $giftCard = GiftCard::query()
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if (! $giftCard) {
                throw new DomainException('Vale-presente não encontrado. Verifique o código digitado.');
            }

            if ($giftCard->status === GiftCard::STATUS_REDEEMED) {
                throw new DomainException('Este vale-presente já foi resgatado.');
            }

            if (! $giftCard->isRedeemable()) {
                throw new DomainException('Este vale-presente não está disponível para resgate ou expirou.');
            }

            $amountCents = CartPricing::toCents((float) $giftCard->remaining_amount);

            $lockedCard = VirtualCard::query()
                ->whereKey($card->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ledger->credit(
                lockedCard: $lockedCard,
                amountCents: $amountCents,
                type: CardTransaction::TYPE_GIFT_CARD_REDEEM,
                description: "Resgate de Vale-Presente {$giftCard->code}",
                reference: $giftCard,
                idempotencyKey: "giftcard-redeem-{$giftCard->id}",
            );

            $giftCard->update([
                'redeemer_user_id' => $redeemer->id,
                'remaining_amount' => 0.00,
                'status' => GiftCard::STATUS_REDEEMED,
                'redeemed_at' => now(),
            ]);

            AuditLog::record(
                'gift_card.redeemed',
                $giftCard,
                null,
                [
                    'code' => $giftCard->code,
                    'redeemer_id' => $redeemer->id,
                    'amount' => CartPricing::toDecimal($amountCents),
                ],
                $redeemer->id
            );

            return $giftCard->fresh();
        });
    }
}
