<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use App\Services\CartPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_integer_cents_prorated_discount_b2b_costs_and_price_warnings(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Testes B2B', 'slug' => 'testes-b2b-unit']);

        $prodA = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Insumo A',
            'slug' => 'insumo-a-unit',
            'cost_price' => 60.00,
            'inbound_freight' => 5.00,
            'other_costs' => 0.00,
            'das_rate' => 4.00,
            'price' => 100.00,
            'stock' => 5,
            'is_active' => true,
        ]);

        $prodB = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Insumo B',
            'slug' => 'insumo-b-unit',
            'cost_price' => 120.00,
            'inbound_freight' => 10.00,
            'other_costs' => 0.00,
            'das_rate' => 4.00,
            'price' => 200.00,
            'stock' => 5,
            'is_active' => true,
        ]);

        Coupon::updateOrCreate(
            ['code' => 'UNIT30'],
            [
                'label' => 'R$ 30 OFF',
                'type' => 'fixed',
                'value' => 30.00,
                'min_total' => 100.00,
                'max_uses' => 10,
                'per_user_limit' => 2,
                'is_active' => true,
            ]
        );

        $pricing = app(CartPricing::class);
        $summary = $pricing->calculate(
            rawCart: [
                $prodA->id => ['product_id' => $prodA->id, 'quantity' => 1, 'price_snapshot' => 90.00],
                $prodB->id => ['product_id' => $prodB->id, 'quantity' => 1, 'price_snapshot' => 200.00],
            ],
            couponCode: 'unit30',
            user: $user,
            cep: '57000-000',
        );

        // Subtotal = 300.00; desconto = 30.00; líquido = 270.00 (< 500 -> frete AL 24.90); total = 294.90
        $this->assertSame(30000, $summary['subtotal_cents']);
        $this->assertSame(3000, $summary['discount_cents']);
        $this->assertSame(2490, $summary['shipping_cents']);
        $this->assertSame(29490, $summary['total_cents']);
        $this->assertSame(294.90, $summary['credits_required']);

        // Rateio do desconto: Insumo A (1/3) = 10.00; Insumo B (2/3) = 20.00
        $this->assertSame(1000, $summary['items'][0]['discount_cents']);
        $this->assertSame(2000, $summary['items'][1]['discount_cents']);

        // Custos totais (65 + 130 = 195), DAS estimado e margem B2B
        $this->assertSame(195.00, $summary['totalCost']);
        $this->assertGreaterThan(0.0, $summary['estimatedDas']);
        $this->assertGreaterThan(0.0, $summary['estimatedMargin']);
        $this->assertCount(1, $summary['price_warnings']);
    }

    public function test_hamilton_proration_distributes_exact_discount_cents_without_remainder_bias(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Rateio B2B', 'slug' => 'rateio-b2b-unit']);

        $items = [];
        foreach ([33.33, 33.33, 33.34] as $index => $price) {
            $prod = Product::factory()->create([
                'category_id' => $category->id,
                'name' => 'Item '.$index,
                'slug' => 'item-rateio-'.$index,
                'cost_price' => 15.00,
                'inbound_freight' => 1.00,
                'das_rate' => 4.00,
                'price' => $price,
                'stock' => 10,
                'is_active' => true,
            ]);
            $items[$prod->id] = ['product_id' => $prod->id, 'quantity' => 1];
        }

        Coupon::updateOrCreate(
            ['code' => 'EXACT10'],
            [
                'label' => 'R$ 10 OFF',
                'type' => 'fixed',
                'value' => 10.00,
                'min_total' => 50.00,
                'max_uses' => 10,
                'per_user_limit' => 2,
                'is_active' => true,
            ]
        );

        $summary = app(CartPricing::class)->calculate(
            rawCart: $items,
            couponCode: 'EXACT10',
            user: $user,
        );

        $this->assertSame(1000, $summary['discount_cents']);
        $sumLineDiscounts = array_sum(array_column($summary['items'], 'discount_cents'));
        $this->assertSame($summary['discount_cents'], $sumLineDiscounts);

        foreach ($summary['items'] as $line) {
            $this->assertGreaterThanOrEqual(0, $line['paid_subtotal_cents']);
            $this->assertLessThanOrEqual($line['subtotal_cents'], $line['discount_cents']);
        }
    }
}
