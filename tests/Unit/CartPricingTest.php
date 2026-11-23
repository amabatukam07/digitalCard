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

    public function test_calculates_integer_cents_prorated_discount_cashback_cap_and_price_warnings(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Testes', 'slug' => 'testes-unit']);

        $prodA = Product::create([
            'category_id' => $category->id,
            'name' => 'Produto A',
            'slug' => 'produto-a-unit',
            'description' => 'Item A',
            'price' => 100.00,
            'cashback_percent' => 10.00,
            'stock' => 5,
            'is_active' => true,
        ]);

        // Cashback acima do teto config('digitalcard.max_cashback_percent', 15.0) deve ser limitado a 15%
        $prodB = Product::create([
            'category_id' => $category->id,
            'name' => 'Produto B',
            'slug' => 'produto-b-unit',
            'description' => 'Item B',
            'price' => 200.00,
            'cashback_percent' => 40.00,
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

        // Subtotal = 300.00; desconto = 30.00 (10% do subtotal); líquido = 270.00 (< 299 -> frete AL 14.90)
        $this->assertSame(30000, $summary['subtotal_cents']);
        $this->assertSame(3000, $summary['discount_cents']);
        $this->assertSame(1490, $summary['shipping_cents']);
        $this->assertSame(28490, $summary['total_cents']);

        // Rateio do desconto: Prod A (1/3) = 10.00 (pago 90.00 -> CB 10% = 9.00); Prod B (2/3) = 20.00 (pago 180.00 -> CB 15% = 27.00)
        $this->assertSame(1000, $summary['items'][0]['discount_cents']);
        $this->assertSame(2000, $summary['items'][1]['discount_cents']);
        $this->assertSame(15.0, $summary['items'][1]['cashback_percent']);
        $this->assertSame(3600, $summary['cashback_total_cents']);
        $this->assertCount(1, $summary['price_warnings']);
    }
}
