<?php

namespace App\Services;

use App\Models\CardTransaction;
use App\Models\VirtualCard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class WalletLedgerService
{
    /**
     * Credita valor em centavos inteiros no cartão virtual com idempotência no ledger.
     */
    public function credit(
        VirtualCard $lockedCard,
        int $amountCents,
        string $type,
        string $description,
        ?Model $reference = null,
        ?string $idempotencyKey = null,
        int $cashbackDeltaCents = 0,
    ): CardTransaction {
        if ($idempotencyKey !== null) {
            $existing = CardTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $currentCents = CartPricing::toCents((float) $lockedCard->balance);
        $newBalanceCents = $currentCents + $amountCents;
        $newBalance = CartPricing::toDecimal($newBalanceCents);

        $attributes = ['balance' => $newBalance];
        if ($cashbackDeltaCents !== 0) {
            $currentCashbackCents = CartPricing::toCents((float) $lockedCard->cashback_earned);
            $attributes['cashback_earned'] = CartPricing::toDecimal(max(0, $currentCashbackCents + $cashbackDeltaCents));
        }

        $lockedCard->update($attributes);

        return CardTransaction::create([
            'virtual_card_id' => $lockedCard->id,
            'type' => $type,
            'amount' => CartPricing::toDecimal($amountCents),
            'balance_after' => $newBalance,
            'description' => $description,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference ? (int) $reference->getKey() : null,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /**
     * Debita valor em centavos inteiros do cartão virtual com idempotência no ledger.
     */
    public function debit(
        VirtualCard $lockedCard,
        int $amountCents,
        string $type,
        string $description,
        ?Model $reference = null,
        ?string $idempotencyKey = null,
        bool $allowPartialToZero = false,
        int $cashbackDeltaCents = 0,
    ): CardTransaction {
        if ($idempotencyKey !== null) {
            $existing = CardTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $currentCents = CartPricing::toCents((float) $lockedCard->balance);

        if (! $allowPartialToZero && $currentCents < $amountCents) {
            throw ValidationException::withMessages([
                'balance' => 'Saldo insuficiente no cartão virtual. Faça uma recarga via PIX ou envie um comprovante para continuar.',
            ]);
        }

        $actualDebitCents = $allowPartialToZero ? min($currentCents, $amountCents) : $amountCents;
        $newBalanceCents = $currentCents - $actualDebitCents;
        $newBalance = CartPricing::toDecimal($newBalanceCents);

        $attributes = ['balance' => $newBalance];
        if ($cashbackDeltaCents !== 0) {
            $currentCashbackCents = CartPricing::toCents((float) $lockedCard->cashback_earned);
            $attributes['cashback_earned'] = CartPricing::toDecimal(max(0, $currentCashbackCents + $cashbackDeltaCents));
        }

        $lockedCard->update($attributes);

        return CardTransaction::create([
            'virtual_card_id' => $lockedCard->id,
            'type' => $type,
            'amount' => -CartPricing::toDecimal($actualDebitCents),
            'balance_after' => $newBalance,
            'description' => $description,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference ? (int) $reference->getKey() : null,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /**
     * Total movimentado em débitos de compras, transferências P2P e gift cards no dia atual (centavos inteiros).
     */
    public function dailySpentCents(VirtualCard $card): int
    {
        $spent = $card->transactions()
            ->whereIn('type', [
                CardTransaction::TYPE_PURCHASE,
                CardTransaction::TYPE_TRANSFER_OUT,
                CardTransaction::TYPE_GIFT_CARD_PURCHASE,
            ])
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('amount');

        return abs(CartPricing::toCents((float) $spent));
    }

    /**
     * Reconcilia o saldo atual do cartão com o somatório das movimentações no ledger.
     *
     * @return array{card_id: int, card_balance_cents: int, ledger_sum_cents: int, difference_cents: int, balanced: bool}
     */
    public function reconcile(VirtualCard $card): array
    {
        $cardBalanceCents = CartPricing::toCents((float) $card->balance);
        $ledgerSumCents = CartPricing::toCents((float) $card->transactions()->sum('amount'));
        $differenceCents = $cardBalanceCents - $ledgerSumCents;

        return [
            'card_id' => (int) $card->id,
            'card_balance_cents' => $cardBalanceCents,
            'ledger_sum_cents' => $ledgerSumCents,
            'difference_cents' => $differenceCents,
            'balanced' => $differenceCents === 0,
        ];
    }
}
