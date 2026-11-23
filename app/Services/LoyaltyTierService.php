<?php

namespace App\Services;

use App\Enums\LoyaltyTier;
use App\Models\Order;
use App\Models\User;

class LoyaltyTierService
{
    /**
     * Resolve o nível de fidelidade do usuário com base no volume acumulado de pedidos entregues.
     *
     * @return array{
     *     tier: LoyaltyTier,
     *     tier_label: string,
     *     delivered_spend: float,
     *     delivered_spend_cents: int,
     *     bonus_cashback_percent: float,
     *     free_shipping_min: float,
     *     free_shipping_min_cents: int,
     *     next_tier: ?LoyaltyTier,
     *     next_tier_label: ?string,
     *     remaining_to_next: float,
     *     remaining_to_next_cents: int,
     *     progress_percent: int
     * }
     */
    public function summaryFor(?User $user): array
    {
        if (! $user) {
            return $this->buildSummary(0);
        }

        $deliveredTotal = (float) $user->orders()
            ->where('status', Order::STATUS_DELIVERED)
            ->sum('total_amount');

        return $this->buildSummary(CartPricing::toCents($deliveredTotal));
    }

    /**
     * @return array{
     *     tier: LoyaltyTier,
     *     tier_label: string,
     *     delivered_spend: float,
     *     delivered_spend_cents: int,
     *     bonus_cashback_percent: float,
     *     free_shipping_min: float,
     *     free_shipping_min_cents: int,
     *     next_tier: ?LoyaltyTier,
     *     next_tier_label: ?string,
     *     remaining_to_next: float,
     *     remaining_to_next_cents: int,
     *     progress_percent: int
     * }
     */
    public function buildSummary(int $deliveredSpendCents): array
    {
        $deliveredSpendCents = max(0, $deliveredSpendCents);
        $tier = LoyaltyTier::fromDeliveredSpendCents($deliveredSpendCents);
        $nextTier = $tier->nextTier();

        if ($nextTier === null) {
            $remainingCents = 0;
            $progress = 100;
        } else {
            $floorCents = $tier->minDeliveredSpendCents();
            $ceilCents = $nextTier->minDeliveredSpendCents();
            $span = max(1, $ceilCents - $floorCents);
            $remainingCents = max(0, $ceilCents - $deliveredSpendCents);
            $progress = (int) min(100, max(0, round((($deliveredSpendCents - $floorCents) / $span) * 100)));
        }

        return [
            'tier' => $tier,
            'tier_label' => $tier->label(),
            'delivered_spend' => CartPricing::toDecimal($deliveredSpendCents),
            'delivered_spend_cents' => $deliveredSpendCents,
            'bonus_cashback_percent' => $tier->bonusCashbackPercent(),
            'free_shipping_min' => CartPricing::toDecimal($tier->freeShippingMinCents()),
            'free_shipping_min_cents' => $tier->freeShippingMinCents(),
            'next_tier' => $nextTier,
            'next_tier_label' => $nextTier?->label(),
            'remaining_to_next' => CartPricing::toDecimal($remainingCents),
            'remaining_to_next_cents' => $remainingCents,
            'progress_percent' => $progress,
        ];
    }
}
