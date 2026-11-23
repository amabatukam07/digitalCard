<?php

namespace App\Actions\Wallet;

use App\Models\AuditLog;
use App\Models\CardTransaction;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Models\VirtualCard;
use App\Services\CartPricing;
use App\Services\WalletLedgerService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WithdrawSavingsGoalAction
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    public function execute(User $user, SavingsGoal $goal, float $amount): SavingsGoal
    {
        $amountCents = CartPricing::toCents($amount);

        if ($amountCents < 100) {
            throw new DomainException('O valor mínimo de resgate do Cofrinho é R$ 1,00.');
        }

        $card = $user->virtualCard()->firstOrCreate([], [
            'card_number' => VirtualCard::generateUniqueCardNumber(),
            'cvv' => VirtualCard::generateCvv(),
            'expiration_date' => VirtualCard::DEFAULT_EXPIRATION_DATE,
            'balance' => 0.00,
            'cashback_earned' => 0.00,
            'is_active' => true,
        ]);

        return DB::transaction(function () use ($user, $card, $goal, $amountCents): SavingsGoal {
            $lockedCard = VirtualCard::query()
                ->whereKey($card->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedGoal = SavingsGoal::query()
                ->whereKey($goal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedGoal->user_id !== (int) $user->id) {
                throw new DomainException('Você não tem permissão para resgatar esta meta.');
            }

            $currentCents = CartPricing::toCents((float) $lockedGoal->current_amount);
            if ($amountCents > $currentCents) {
                throw new DomainException('O valor solicitado excede o saldo reservado nesta meta do Cofrinho.');
            }

            $newCurrentCents = $currentCents - $amountCents;
            $targetCents = CartPricing::toCents((float) $lockedGoal->target_amount);

            $this->ledger->credit(
                lockedCard: $lockedCard,
                amountCents: $amountCents,
                type: CardTransaction::TYPE_GOAL_WITHDRAW,
                description: "Resgate do Cofrinho: {$lockedGoal->name}",
                reference: $lockedGoal,
                idempotencyKey: 'goal-wd-'.$lockedGoal->id.'-'.(string) Str::uuid(),
            );

            $lockedGoal->update([
                'current_amount' => CartPricing::toDecimal($newCurrentCents),
                'completed_at' => $newCurrentCents >= $targetCents ? $lockedGoal->completed_at : null,
            ]);

            AuditLog::record(
                'savings_goal.withdraw',
                $lockedGoal,
                null,
                [
                    'amount' => CartPricing::toDecimal($amountCents),
                    'current_amount' => (float) $lockedGoal->current_amount,
                ],
                $user->id
            );

            return $lockedGoal->fresh('product');
        });
    }
}
