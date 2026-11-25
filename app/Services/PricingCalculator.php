<?php

namespace App\Services;

use App\Models\Product;

class PricingCalculator
{
    public function totalUnitCost(float $costPrice, float $inboundFreight, float $otherCosts = 0.0): float
    {
        return round(max(0.0, $costPrice) + max(0.0, $inboundFreight) + max(0.0, $otherCosts), 2);
    }

    /**
     * Fórmula gerencial: Preço = C * (1 + m) / (1 - t - v), com arredondamento opcional para cima de R$ 0,10.
     */
    public function suggestedPrice(
        float $totalUnitCost,
        float $markupPercent = 35.0,
        float $dasRatePercent = 4.0,
        float $variableExpensesPercent = 0.0,
        bool $roundUpToTenCents = true,
    ): float {
        $cost = max(0.0, $totalUnitCost);
        $m = max(0.0, $markupPercent) / 100.0;
        $t = max(0.0, $dasRatePercent) / 100.0;
        $v = max(0.0, $variableExpensesPercent) / 100.0;

        $denominator = max(0.05, 1.0 - $t - $v);
        $raw = ($cost * (1.0 + $m)) / $denominator;

        if (! $roundUpToTenCents) {
            return round($raw, 2);
        }

        return round(ceil(round($raw * 10, 6)) / 10, 2);
    }

    /**
     * @return array{
     *   sale_price: float,
     *   total_cost: float,
     *   das_rate: float,
     *   das_amount: float,
     *   result_after_das: float,
     *   margin_percent: float,
     *   effective_markup_percent: float
     * }
     */
    public function analyze(float $salePrice, float $totalUnitCost, float $dasRatePercent = 4.0): array
    {
        $sale = round(max(0.0, $salePrice), 2);
        $cost = round(max(0.0, $totalUnitCost), 2);
        $rate = max(0.0, $dasRatePercent);

        $dasAmount = round($sale * ($rate / 100.0), 2);
        $resultAfterDas = round($sale - $cost - $dasAmount, 2);
        $marginPercent = $sale > 0.0 ? round(($resultAfterDas / $sale) * 100.0, 2) : 0.0;
        $effectiveMarkupPercent = $cost > 0.0 ? round((($sale - $cost) / $cost) * 100.0, 2) : 0.0;

        return [
            'sale_price' => $sale,
            'total_cost' => $cost,
            'das_rate' => round($rate, 2),
            'das_amount' => $dasAmount,
            'result_after_das' => $resultAfterDas,
            'margin_percent' => $marginPercent,
            'effective_markup_percent' => $effectiveMarkupPercent,
        ];
    }

    /**
     * Executa simulação ilustrativa de mix de compras sobre uma carteira de créditos (Seção 8 da documentação).
     *
     * @return array{
     *   credits_available: float,
     *   simulated_sales: float,
     *   merchandise_and_freight_cost: float,
     *   estimated_das: float,
     *   result_after_cost_and_das: float,
     *   unused_credits: float,
     *   estimated_margin_percent: float,
     *   items: list<array{
     *     name: string,
     *     presentation: string,
     *     quantity: int,
     *     unit_cost: float,
     *     unit_price: float,
     *     line_cost: float,
     *     line_sales: float
     *   }>
     * }
     */
    public function simulatePortfolio(float $credits = 1000.0, float $markupPercent = 35.0, float $dasRatePercent = 4.0): array
    {
        $targetMix = [
            'copo-descartavel-180ml' => 8,
            'agua-mineral-copo-200ml' => 4,
            'agua-mineral-garrafa-500ml' => 5,
            'papel-a4-500-folhas' => 6,
            'detergente-500ml-cx12' => 3,
            'papel-toalha-2-rolos' => 8,
            'papel-higienico-12-rolos' => 5,
            'saco-lixo-100l-100un' => 4,
            'desinfetante-2l' => 5,
            'cafe-500g' => 4,
        ];

        $products = Product::query()
            ->whereIn('slug', array_keys($targetMix))
            ->get()
            ->keyBy('slug');

        $creditsCents = CartPricing::toCents($credits);
        $catalogEntries = [];
        $baseBasketCents = 0;

        foreach ($targetMix as $slug => $baseQty) {
            /** @var Product|null $product */
            $product = $products->get($slug);
            if (! $product) {
                continue;
            }

            $unitCost = $product->total_unit_cost;
            $unitPrice = $this->suggestedPrice($unitCost, $markupPercent, $dasRatePercent, 0.0, true);
            $unitPriceCents = max(1, CartPricing::toCents($unitPrice));
            $unitCostCents = CartPricing::toCents($unitCost);

            $baseBasketCents += $unitPriceCents * $baseQty;
            $catalogEntries[$slug] = [
                'product' => $product,
                'base_qty' => $baseQty,
                'unit_cost' => $unitCost,
                'unit_price' => $unitPrice,
                'unit_cost_cents' => $unitCostCents,
                'unit_price_cents' => $unitPriceCents,
                'qty' => 0,
            ];
        }

        $scaleFactor = $baseBasketCents > 0 ? ($creditsCents / $baseBasketCents) : 1.0;
        $totalSalesCents = 0;
        $totalCostCents = 0;

        // Primeira passada: alocação proporcional preservando amplitude do mix
        foreach ($catalogEntries as &$entry) {
            $targetQty = (int) floor($entry['base_qty'] * $scaleFactor);
            if ($targetQty < 1 && ($totalSalesCents + $entry['unit_price_cents']) <= $creditsCents) {
                $targetQty = 1;
            }

            if ($targetQty < 1) {
                continue;
            }

            $affordable = (int) floor(($creditsCents - $totalSalesCents) / $entry['unit_price_cents']);
            $qty = min($targetQty, max(0, $affordable));
            if ($qty < 1) {
                continue;
            }

            $entry['qty'] = $qty;
            $totalSalesCents += $entry['unit_price_cents'] * $qty;
            $totalCostCents += $entry['unit_cost_cents'] * $qty;
        }
        unset($entry);

        // Segunda passada: preenche saldo residual por déficit proporcional
        while ($catalogEntries !== []) {
            $bestSlug = null;
            $bestDeficit = -INF;

            foreach ($catalogEntries as $slug => $entry) {
                if (($totalSalesCents + $entry['unit_price_cents']) > $creditsCents) {
                    continue;
                }

                $idealQty = $entry['base_qty'] * $scaleFactor;
                $deficit = ($idealQty - $entry['qty']) / max(1, $entry['base_qty']);
                if ($deficit > $bestDeficit) {
                    $bestDeficit = $deficit;
                    $bestSlug = $slug;
                }
            }

            if ($bestSlug === null) {
                break;
            }

            $catalogEntries[$bestSlug]['qty']++;
            $totalSalesCents += $catalogEntries[$bestSlug]['unit_price_cents'];
            $totalCostCents += $catalogEntries[$bestSlug]['unit_cost_cents'];
        }

        $items = [];
        foreach ($catalogEntries as $entry) {
            if ($entry['qty'] < 1) {
                continue;
            }

            $qty = $entry['qty'];
            $items[] = [
                'name' => $entry['product']->name,
                'presentation' => (string) ($entry['product']->presentation ?: $entry['product']->unit),
                'quantity' => $qty,
                'unit_cost' => $entry['unit_cost'],
                'unit_price' => $entry['unit_price'],
                'line_cost' => CartPricing::toDecimal($entry['unit_cost_cents'] * $qty),
                'line_sales' => CartPricing::toDecimal($entry['unit_price_cents'] * $qty),
            ];
        }

        $sales = CartPricing::toDecimal($totalSalesCents);
        $cost = CartPricing::toDecimal($totalCostCents);
        $das = round($sales * ($dasRatePercent / 100.0), 2);
        $result = round($sales - $cost - $das, 2);
        $unused = round(max(0.0, $credits - $sales), 2);
        $margin = $sales > 0.0 ? round(($result / $sales) * 100.0, 2) : 0.0;

        return [
            'credits_available' => round($credits, 2),
            'simulated_sales' => $sales,
            'merchandise_and_freight_cost' => $cost,
            'estimated_das' => $das,
            'result_after_cost_and_das' => $result,
            'unused_credits' => $unused,
            'estimated_margin_percent' => $margin,
            'items' => $items,
        ];
    }
}
