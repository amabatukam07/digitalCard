<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Notifications\LowBalanceNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class WalletLedgerService
{
    /**
     * Credita valor em centavos inteiros na carteira de créditos da empresa com idempotência.
     */
    public function credit(
        CreditWallet $lockedWallet,
        int $amountCents,
        string $type,
        string $description,
        ?Model $reference = null,
        ?string $idempotencyKey = null,
        ?int $actorUserId = null,
        ?string $reason = null,
    ): CreditTransaction {
        if ($idempotencyKey !== null) {
            $existing = CreditTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $beforeCents = CartPricing::toCents((float) $lockedWallet->balance);
        $afterCents = $beforeCents + $amountCents;

        $attributes = [
            'balance' => CartPricing::toDecimal($afterCents),
        ];

        if ($type === CreditTransaction::TYPE_CREDIT_PURCHASE) {
            $acquiredCents = CartPricing::toCents((float) $lockedWallet->total_acquired) + $amountCents;
            $attributes['total_acquired'] = CartPricing::toDecimal($acquiredCents);
            $attributes['low_balance_notified_at'] = null;
        } elseif (in_array($type, [CreditTransaction::TYPE_ORDER_REFUND, CreditTransaction::TYPE_ORDER_RETURN_REFUND], true)) {
            $spentCents = max(0, CartPricing::toCents((float) $lockedWallet->total_spent) - $amountCents);
            $attributes['total_spent'] = CartPricing::toDecimal($spentCents);
        }

        $lockedWallet->update($attributes);

        return CreditTransaction::create([
            'credit_wallet_id' => $lockedWallet->id,
            'company_id' => $lockedWallet->company_id,
            'user_id' => $actorUserId,
            'type' => $type,
            'amount' => CartPricing::toDecimal($amountCents),
            'balance_before' => CartPricing::toDecimal($beforeCents),
            'balance_after' => CartPricing::toDecimal($afterCents),
            'description' => $description,
            'reason' => $reason,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference ? (int) $reference->getKey() : null,
            'idempotency_key' => $idempotencyKey,
            'created_at' => now(),
        ]);
    }

    /**
     * Debita valor em centavos inteiros da carteira de créditos da empresa (bloqueia saldo negativo).
     */
    public function debit(
        CreditWallet $lockedWallet,
        int $amountCents,
        string $type,
        string $description,
        ?Model $reference = null,
        ?string $idempotencyKey = null,
        ?int $actorUserId = null,
        ?string $reason = null,
    ): CreditTransaction {
        if ($idempotencyKey !== null) {
            $existing = CreditTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $beforeCents = CartPricing::toCents((float) $lockedWallet->balance);

        if ($beforeCents < $amountCents) {
            throw ValidationException::withMessages([
                'balance' => 'Saldo de créditos insuficiente na carteira da empresa. Contrate novos créditos para confirmar este pedido.',
            ]);
        }

        $afterCents = $beforeCents - $amountCents;

        $attributes = [
            'balance' => CartPricing::toDecimal($afterCents),
        ];

        if ($type === CreditTransaction::TYPE_ORDER_DEBIT) {
            $spentCents = CartPricing::toCents((float) $lockedWallet->total_spent) + $amountCents;
            $attributes['total_spent'] = CartPricing::toDecimal($spentCents);
        }

        $lockedWallet->update($attributes);

        return CreditTransaction::create([
            'credit_wallet_id' => $lockedWallet->id,
            'company_id' => $lockedWallet->company_id,
            'user_id' => $actorUserId,
            'type' => $type,
            'amount' => -CartPricing::toDecimal($amountCents),
            'balance_before' => CartPricing::toDecimal($beforeCents),
            'balance_after' => CartPricing::toDecimal($afterCents),
            'description' => $description,
            'reason' => $reason,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference ? (int) $reference->getKey() : null,
            'idempotency_key' => $idempotencyKey,
            'created_at' => now(),
        ]);
    }

    public function dailySpentCents(CreditWallet $wallet): int
    {
        $spent = $wallet->transactions()
            ->where('type', CreditTransaction::TYPE_ORDER_DEBIT)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('amount');

        return abs(CartPricing::toCents((float) $spent));
    }

    /**
     * Concilia o saldo atual da carteira com o somatório das movimentações no extrato.
     *
     * @return array{wallet_id: int, company_id: int, wallet_balance_cents: int, ledger_sum_cents: int, difference_cents: int, balanced: bool}
     */
    public function reconcile(CreditWallet $wallet): array
    {
        $walletBalanceCents = CartPricing::toCents((float) $wallet->balance);
        $ledgerSumCents = CartPricing::toCents((float) $wallet->transactions()->sum('amount'));
        $differenceCents = $walletBalanceCents - $ledgerSumCents;

        return [
            'wallet_id' => (int) $wallet->id,
            'company_id' => (int) $wallet->company_id,
            'wallet_balance_cents' => $walletBalanceCents,
            'ledger_sum_cents' => $ledgerSumCents,
            'difference_cents' => $differenceCents,
            'balanced' => $differenceCents === 0,
        ];
    }

    /**
     * Notifica os gestores da empresa quando o saldo atingir o limite de alerta configurado.
     */
    public function notifyIfLowBalance(CreditWallet $wallet, Company $company): void
    {
        $threshold = (float) $company->low_balance_threshold;
        $balance = (float) $wallet->balance;

        if ($threshold <= 0.0 || $balance > $threshold) {
            return;
        }

        if ($wallet->low_balance_notified_at && $wallet->low_balance_notified_at->gt(now()->subHours(12))) {
            return;
        }

        $wallet->update(['low_balance_notified_at' => now()]);

        $managers = $company->users()
            ->wherePivot('company_role', CompanyUser::ROLE_MANAGER)
            ->wherePivot('is_active', true)
            ->get();

        foreach ($managers as $manager) {
            $manager->notify(new LowBalanceNotification($company, $balance, $threshold));
        }
    }
}
