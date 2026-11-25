<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_idempotency_key_prevents_duplicate_orders(): void
    {
        $company = Company::factory()->create(['require_order_approval' => false]);
        $company->wallet()->update(['balance' => 1000.00, 'total_acquired' => 1000.00]);
        $customer = User::factory()->forCompany($company)->create();

        [$product] = $this->createProduct(['price' => 300.00, 'stock' => 5]);

        $payload = [
            'shipping_address' => 'Av. Idempotente, 100 - São Paulo/SP',
            'idempotency_key' => 'idem-token-unique-001',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ];

        $first = $this->actingAs($customer)->post(route('orders.checkout'), $payload);
        $order = Order::firstOrFail();
        $first->assertRedirect(route('orders.show', $order));

        $second = $this->actingAs($customer)->post(route('orders.checkout'), $payload);
        $second->assertRedirect(route('orders.show', $order));

        $this->assertEquals(1, Order::count());
        $this->assertEquals(700.00, (float) $company->wallet->fresh()->balance);
        $this->assertEquals(4, $product->fresh()->stock);
    }

    public function test_order_status_transitions_delivery_and_seven_day_regret_return(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::factory()->create(['require_order_approval' => false]);
        $company->wallet()->update(['balance' => 500.00, 'total_acquired' => 500.00]);
        $customer = User::factory()->forCompany($company)->create();

        [$product] = $this->createProduct(['price' => 200.00, 'stock' => 5]);

        $this->actingAs($customer)->post(route('orders.checkout'), [
            'shipping_address' => 'Rua Fluxo, 10 - São Paulo/SP',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $order = Order::firstOrFail();

        $this->actingAs($admin)->patch(route('admin.orders.status', $order), [
            'status' => OrderStatus::Separating->value,
        ])->assertSessionHas('success');
        $this->assertEquals(OrderStatus::Separating->value, $order->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.orders.status', $order), [
            'status' => OrderStatus::Shipped->value,
            'tracking_code' => 'BR998877665',
        ])->assertSessionHas('success');
        $this->assertEquals(OrderStatus::Shipped->value, $order->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.orders.status', $order), [
            'status' => OrderStatus::Delivered->value,
            'receiver_name' => 'Portaria Matriz',
        ])->assertSessionHas('success');

        $delivered = $order->fresh();
        $this->assertEquals(OrderStatus::Delivered->value, $delivered->status);
        $this->assertEquals(300.00, (float) $company->wallet->fresh()->balance);
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'receiver_name' => 'Portaria Matriz',
        ]);

        // Devolução dentro de 7 dias estorna 100% dos créditos (1:1) e recompõe estoque
        $this->actingAs($customer)
            ->post(route('orders.return', $order))
            ->assertSessionHas('success');

        $this->assertEquals(Order::STATUS_RETURNED, $order->fresh()->status);
        $this->assertEquals(500.00, (float) $company->wallet->fresh()->balance);
        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_ORDER_RETURN_IN,
            'quantity' => 1,
        ]);
    }

    public function test_identity_honesty_real_histogram_and_portuguese_copy(): void
    {
        $this->seed(CatalogSeeder::class);

        $home = $this->get(route('catalog.index'));
        $home->assertOk();
        $home->assertSee('NexoSupri');
        $home->assertDontSee('DigitalCard', false);
        $home->assertDontSee('cardstore', false);
        $home->assertDontSee('SIGCAP', false);
        $home->assertDontSee('cashback', false);

        $product = Product::firstOrFail();
        $show = $this->get(route('catalog.product', $product->slug));
        $show->assertOk();
        $show->assertSee('Ainda sem avaliações registradas após entrega.');
        $show->assertSee('Insumos relacionados');
        $show->assertDontSee('Quem viu este produto também comprou');
    }

    public function test_product_review_rating_aggregates_real_reviews_from_database(): void
    {
        $this->seed(CatalogSeeder::class);
        $product = Product::firstOrFail();

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $product->reviews()->create([
            'user_id' => $u1->id,
            'rating' => 5,
            'comment' => 'Excelente acabamento e entrega no prazo.',
            'is_verified_purchase' => true,
        ]);
        $product->reviews()->create([
            'user_id' => $u2->id,
            'rating' => 3,
            'comment' => 'Bom custo-benefício.',
            'is_verified_purchase' => true,
        ]);
        $product->recalculateRating();

        $response = $this->get(route('catalog.product', $product->slug));
        $response->assertOk();
        $response->assertSee('4,0');
        $response->assertSee('2 avaliações verificadas');
        $response->assertSee('Nota 5/5');
        $response->assertSee('Nota 3/5');
        $response->assertSee('Excelente acabamento e entrega no prazo.');
        $response->assertSee('"@type":"Product"', false);
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
