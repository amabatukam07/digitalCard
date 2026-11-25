<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_coupon_chips_render_actual_coupon_codes_and_apply_correctly(): void
    {
        $company = Company::factory()->create();
        $customer = User::factory()->forCompany($company)->create();
        [$product] = $this->createProduct(['price' => 600.00, 'stock' => 5]);

        Coupon::updateOrCreate(
            ['code' => 'NEXO5'],
            [
                'label' => '5% OFF Corporativo',
                'type' => 'percent',
                'value' => 5.00,
                'min_total' => 100.00,
                'max_uses' => 50,
                'per_user_limit' => 2,
                'is_active' => true,
            ]
        );

        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 1]);

        $cartPage = $this->actingAs($customer)->get(route('cart.index'));
        $cartPage->assertOk();

        // Deve renderizar o código real do model Coupon (NEXO5) e jamais value="0"
        $cartPage->assertSee('ex: NEXO5');
        $cartPage->assertDontSee('name="coupon_code" value="0"', false);

        $this->actingAs($customer)
            ->post(route('cart.coupon.apply'), ['coupon_code' => 'NEXO5'])
            ->assertSessionHas('success');

        $this->assertEquals('NEXO5', session('cart_coupon'));

        $cartAfter = $this->actingAs($customer)->get(route('cart.index'));
        $cartAfter->assertOk();
        $cartAfter->assertSee('value="NEXO5"', false);
    }

    public function test_coupon_applies_to_corporate_credit_checkout_and_respects_limits(): void
    {
        $company = Company::factory()->create(['require_order_approval' => false]);
        $company->wallet()->update(['balance' => 1000.00, 'total_acquired' => 1000.00]);
        $customer = User::factory()->forCompany($company)->create();

        [$product] = $this->createProduct([
            'cost_price' => 120.00,
            'price' => 200.00,
            'stock' => 10,
        ]);

        Coupon::updateOrCreate(
            ['code' => 'DESC50'],
            [
                'label' => 'R$ 50 OFF',
                'type' => 'fixed',
                'value' => 50.00,
                'min_total' => 100.00,
                'max_uses' => 1,
                'used_count' => 0,
                'per_user_limit' => 1,
                'is_active' => true,
            ]
        );

        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 1]);
        $this->actingAs($customer)->post(route('cart.coupon.apply'), ['coupon_code' => 'DESC50'])
            ->assertSessionHas('success');

        $this->actingAs($customer)->post(route('orders.checkout'), [
            'shipping_address' => 'Av. Paulista, 1000 - São Paulo/SP',
        ])->assertSessionHasNoErrors();

        $order = Order::firstOrFail();

        $this->assertEquals(150.00, (float) $order->total_amount);
        $this->assertEquals(150.00, (float) $order->credits_debited);
        $this->assertEquals(850.00, (float) $company->wallet->fresh()->balance);

        // Segunda tentativa de uso deve ser bloqueada pelo limite de utilizações
        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 1]);
        $this->actingAs($customer)->post(route('cart.coupon.apply'), ['coupon_code' => 'DESC50'])
            ->assertSessionHas('error');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: Product, 1: Category}
     */
    private function createProduct(array $attributes = []): array
    {
        $category = Category::create([
            'name' => 'Departamento',
            'slug' => 'dep-'.uniqid(),
        ]);

        $product = Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'name' => 'Item Teste',
            'slug' => 'item-teste-'.uniqid(),
            'description' => 'Descrição item.',
            'cost_price' => 60.00,
            'inbound_freight' => 0.00,
            'price' => 100.00,
            'stock' => 10,
            'is_active' => true,
        ], $attributes));

        return [$product, $category];
    }
}
