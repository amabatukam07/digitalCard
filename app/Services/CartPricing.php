<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

class CartPricing
{
    public static function toCents(float|int|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public static function toDecimal(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * Calcula o resumo do carrinho B2B em centavos inteiros (1 crédito = R$ 1,00, sem bônus ou cashback).
     *
     * @param  array<int|string, array{product_id?: int, quantity: int, price_snapshot?: float|null}>  $rawCart
     * @param  Collection<int, Product>|null  $preloadedProducts
     * @return array{
     *   items: list<array{
     *     product: Product,
     *     quantity: int,
     *     unit_price_cents: int,
     *     unit_price: float,
     *     unit_cost_cents: int,
     *     unit_cost: float,
     *     das_rate: float,
     *     subtotal_cents: int,
     *     subtotal: float,
     *     original_subtotal_cents: int,
     *     original_subtotal: float,
     *     discount_cents: int,
     *     discount: float,
     *     paid_subtotal_cents: int,
     *     line_cost_cents: int,
     *     line_das_cents: int,
     *     price_changed: bool,
     *     previous_price: float|null
     *   }>,
     *   subtotal_cents: int,
     *   subtotalAll: float,
     *   original_total_cents: int,
     *   originalTotal: float,
     *   discount_cents: int,
     *   discountAmount: float,
     *   coupon: Coupon|null,
     *   activeCoupon: string|null,
     *   shipping_cents: int,
     *   shippingCost: float,
     *   total_cents: int,
     *   total: float,
     *   credits_required: float,
     *   total_cost_cents: int,
     *   totalCost: float,
     *   estimated_das_cents: int,
     *   estimatedDas: float,
     *   estimated_margin_cents: int,
     *   estimatedMargin: float,
     *   price_warnings: list<string>
     * }
     */
    public function calculate(
        array $rawCart,
        ?string $couponCode = null,
        ?User $user = null,
        ?string $cep = null,
        ?Collection $preloadedProducts = null,
        bool $lockCoupon = false,
    ): array {
        $productIds = array_map('intval', array_keys($rawCart));
        $products = $preloadedProducts ?? Product::with('category')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $lineItems = [];
        $subtotalCents = 0;
        $originalTotalCents = 0;
        $totalCostCents = 0;
        $priceWarnings = [];

        foreach ($rawCart as $key => $entry) {
            $productId = (int) ($entry['product_id'] ?? $key);
            /** @var Product|null $product */
            $product = $products->get($productId);

            if (! $product || ! $product->is_active || $product->stock < 1) {
                continue;
            }

            $quantity = min(max(1, (int) $entry['quantity']), (int) $product->stock);
            $unitPriceCents = self::toCents((float) $product->price);
            $origUnitCents = self::toCents((float) $product->original_price);
            $unitCostCents = self::toCents($product->total_unit_cost);
            $dasRate = (float) ($product->das_rate ?? config('nexosupri.default_das_rate_percent', 4.0));

            $lineSubtotalCents = $unitPriceCents * $quantity;
            $lineOrigSubtotalCents = $origUnitCents * $quantity;
            $lineCostCents = $unitCostCents * $quantity;

            $priceChanged = false;
            $previousPrice = isset($entry['price_snapshot']) ? (float) $entry['price_snapshot'] : null;

            if ($previousPrice !== null && self::toCents($previousPrice) !== $unitPriceCents) {
                $priceChanged = true;
                $priceWarnings[] = sprintf(
                    'O preço de "%s" foi atualizado de R$ %s para R$ %s.',
                    $product->name,
                    number_format($previousPrice, 2, ',', '.'),
                    number_format(self::toDecimal($unitPriceCents), 2, ',', '.')
                );
            }

            $lineItems[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price_cents' => $unitPriceCents,
                'unit_price' => self::toDecimal($unitPriceCents),
                'unit_cost_cents' => $unitCostCents,
                'unit_cost' => self::toDecimal($unitCostCents),
                'das_rate' => $dasRate,
                'subtotal_cents' => $lineSubtotalCents,
                'subtotal' => self::toDecimal($lineSubtotalCents),
                'original_subtotal_cents' => $lineOrigSubtotalCents,
                'original_subtotal' => self::toDecimal($lineOrigSubtotalCents),
                'discount_cents' => 0,
                'discount' => 0.0,
                'paid_subtotal_cents' => $lineSubtotalCents,
                'line_cost_cents' => $lineCostCents,
                'line_das_cents' => 0,
                'price_changed' => $priceChanged,
                'previous_price' => $previousPrice,
            ];

            $subtotalCents += $lineSubtotalCents;
            $originalTotalCents += $lineOrigSubtotalCents;
            $totalCostCents += $lineCostCents;
        }

        $coupon = null;
        $discountCents = 0;
        $activeCouponCode = null;

        if ($couponCode && $subtotalCents > 0) {
            $normalizedCode = strtoupper(trim($couponCode));
            $couponQuery = Coupon::where('code', $normalizedCode);
            $candidate = $lockCoupon ? $couponQuery->lockForUpdate()->first() : $couponQuery->first();

            if ($candidate && $candidate->isValidForUser($user, $subtotalCents)) {
                $coupon = $candidate;
                $activeCouponCode = $candidate->code;

                if ($candidate->type === Coupon::TYPE_PERCENT) {
                    $discountCents = (int) round($subtotalCents * ((float) $candidate->value / 100.0));
                } else {
                    $discountCents = min($subtotalCents, self::toCents((float) $candidate->value));
                }

                $discountCents = min($discountCents, max(0, $subtotalCents - 1));
            }
        }

        $discountedMerchandiseCents = max(0, $subtotalCents - $discountCents);

        $estimatedDasCents = 0;

        if ($discountCents > 0 && $subtotalCents > 0 && $lineItems !== []) {
            $allocations = [];
            $remainders = [];
            $allocatedTotal = 0;

            foreach ($lineItems as $idx => $line) {
                $exact = ($discountCents * $line['subtotal_cents']) / $subtotalCents;
                $floored = min($line['subtotal_cents'], (int) floor($exact));
                $allocations[$idx] = $floored;
                $remainders[$idx] = [
                    'idx' => $idx,
                    'fraction' => $exact - $floored,
                    'subtotal_cents' => $line['subtotal_cents'],
                ];
                $allocatedTotal += $floored;
            }

            $remaining = max(0, $discountCents - $allocatedTotal);
            if ($remaining > 0) {
                usort($remainders, static function (array $a, array $b): int {
                    $cmp = $b['fraction'] <=> $a['fraction'];
                    if ($cmp !== 0) {
                        return $cmp;
                    }

                    return $b['subtotal_cents'] <=> $a['subtotal_cents'];
                });

                $lineCount = count($remainders);
                $cursor = 0;
                $guard = 0;
                $maxIterations = $remaining + ($lineCount * 2);

                while ($remaining > 0 && $guard < $maxIterations) {
                    $idx = $remainders[$cursor % $lineCount]['idx'];
                    if ($allocations[$idx] < $lineItems[$idx]['subtotal_cents']) {
                        $allocations[$idx]++;
                        $remaining--;
                    }
                    $cursor++;
                    $guard++;
                }
            }

            foreach ($lineItems as $idx => &$line) {
                $itemDiscount = $allocations[$idx] ?? 0;
                $line['discount_cents'] = $itemDiscount;
                $line['discount'] = self::toDecimal($itemDiscount);
                $line['paid_subtotal_cents'] = max(0, $line['subtotal_cents'] - $itemDiscount);
            }
            unset($line);
        }

        foreach ($lineItems as &$line) {
            $lineDasCents = (int) round($line['paid_subtotal_cents'] * ($line['das_rate'] / 100.0));
            $line['line_das_cents'] = $lineDasCents;
            $estimatedDasCents += $lineDasCents;
        }
        unset($line);

        $shippingCents = 0;
        if ($cep !== null && trim($cep) !== '' && $discountedMerchandiseCents > 0) {
            $shippingQuote = app(ShippingCalculator::class)->calculate(
                $cep,
                $discountedMerchandiseCents,
            );
            $shippingCents = $shippingQuote['cost_cents'];
        }

        $totalCents = $discountedMerchandiseCents + $shippingCents;
        $estimatedMarginCents = $discountedMerchandiseCents - $totalCostCents - $estimatedDasCents;

        return [
            'items' => $lineItems,
            'subtotal_cents' => $subtotalCents,
            'subtotalAll' => self::toDecimal($subtotalCents),
            'original_total_cents' => $originalTotalCents,
            'originalTotal' => self::toDecimal($originalTotalCents),
            'discount_cents' => $discountCents,
            'discountAmount' => self::toDecimal($discountCents),
            'coupon' => $coupon,
            'activeCoupon' => $activeCouponCode,
            'shipping_cents' => $shippingCents,
            'shippingCost' => self::toDecimal($shippingCents),
            'total_cents' => $totalCents,
            'total' => self::toDecimal($totalCents),
            'credits_required' => self::toDecimal($totalCents),
            'total_cost_cents' => $totalCostCents,
            'totalCost' => self::toDecimal($totalCostCents),
            'estimated_das_cents' => $estimatedDasCents,
            'estimatedDas' => self::toDecimal($estimatedDasCents),
            'estimated_margin_cents' => $estimatedMarginCents,
            'estimatedMargin' => self::toDecimal($estimatedMarginCents),
            'price_warnings' => $priceWarnings,
        ];
    }
}
