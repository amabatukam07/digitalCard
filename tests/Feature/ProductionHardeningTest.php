<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\CardTransaction;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Recharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_express_checkout_preserves_existing_cart_and_records_immutable_item_snapshots(): void
    {
        $customer = User::factory()->create();
        $customer->virtualCard->update([
            'balance' => 1000.00,
            'security_pin' => Hash::make('1234'),
        ]);

        [$cartProduct] = $this->createProduct(['name' => 'Item no Carrinho', 'price' => 150.00]);
        [$expressProduct] = $this->createProduct(['name' => 'Notebook Pro 16', 'price' => 400.00, 'cashback_percent' => 5.0]);

        $this->actingAs($customer)->post(route('cart.add', $cartProduct), ['quantity' => 2]);

        $this->actingAs($customer)->post(route('orders.checkout'), [
            'shipping_address' => 'Rua Express, 10',
            'shipping_cep' => '57000-000',
            'card_pin' => '1234',
            'items' => [
                ['product_id' => $expressProduct->id, 'quantity' => 1],
            ],
        ])->assertSessionHasNoErrors();

        // Carrinho e cart_items preservados após checkout direto
        $this->assertArrayHasKey($cartProduct->id, session('cart', []));
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $customer->id,
            'product_id' => $cartProduct->id,
            'quantity' => 2,
        ]);

        $order = Order::firstOrFail();
        $item = $order->items()->firstOrFail();

        $this->assertSame('Notebook Pro 16', $item->product_name);
        $this->assertSame('PROD-'.str_pad((string) $expressProduct->id, 4, '0', STR_PAD_LEFT), $item->product_sku);
        $this->assertEquals(5.0, (float) $item->cashback_percent);

        // Renomear o produto no catálogo não altera o snapshot histórico do pedido
        $expressProduct->update(['name' => 'Nome Alterado Depois']);
        $this->assertSame('Notebook Pro 16', $item->fresh()->display_name);
    }

    public function test_cancelling_order_restores_coupon_quota_and_redemption(): void
    {
        $customer = User::factory()->create();
        $customer->virtualCard->update([
            'balance' => 1000.00,
            'security_pin' => Hash::make('1234'),
        ]);

        [$product] = $this->createProduct(['price' => 300.00, 'stock' => 5]);

        $coupon = Coupon::create([
            'code' => 'UNICO50',
            'label' => 'R$ 50 OFF único',
            'type' => 'fixed',
            'value' => 50.00,
            'min_total' => 100.00,
            'max_uses' => 1,
            'used_count' => 0,
            'per_user_limit' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 1]);
        $this->actingAs($customer)->post(route('cart.coupon.apply'), ['coupon_code' => 'UNICO50']);

        $this->actingAs($customer)->post(route('orders.checkout'), [
            'shipping_address' => 'Av. Cupom, 200',
            'card_pin' => '1234',
        ])->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertDatabaseHas('coupon_redemptions', ['order_id' => $order->id]);

        $this->actingAs($customer)->patch(route('orders.cancel', $order))->assertSessionHas('success');

        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertDatabaseMissing('coupon_redemptions', ['order_id' => $order->id]);

        // O cliente volta a poder reutilizar o cupom após cancelamento
        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 1]);
        $this->actingAs($customer)
            ->post(route('cart.coupon.apply'), ['coupon_code' => 'UNICO50'])
            ->assertSessionHas('success');
    }

    public function test_pix_webhook_simulation_forbids_other_customers_and_daily_limit_is_enforced(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($owner)->post(route('card.pix.generate'), ['amount' => 400.00]);
        $charge = $owner->virtualCard->recharges()->firstOrFail();

        $this->actingAs($intruder)
            ->post(route('webhooks.pix.simulate'), ['txid' => $charge->txid])
            ->assertForbidden();

        // Titular consegue simular pagamento e definir limite diário
        $this->actingAs($owner)
            ->post(route('webhooks.pix.simulate'), ['txid' => $charge->txid])
            ->assertSessionHas('success');

        $this->actingAs($owner)->patch(route('card.update-pin'), [
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ]);

        $this->actingAs($owner)->patch(route('card.update-limit'), ['daily_limit' => 250.00])
            ->assertSessionHas('success');

        [$expensive] = $this->createProduct(['price' => 300.00, 'stock' => 5]);
        $this->actingAs($owner)->post(route('orders.checkout'), [
            'shipping_address' => 'Rua Limite, 50',
            'card_pin' => '1234',
            'items' => [['product_id' => $expensive->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('card');

        [$affordable] = $this->createProduct(['price' => 200.00, 'stock' => 5]);
        $this->actingAs($owner)->post(route('orders.checkout'), [
            'shipping_address' => 'Rua Limite, 50',
            'card_pin' => '1234',
            'items' => [['product_id' => $affordable->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();
    }

    public function test_unblocking_card_with_pin_requires_valid_pin_and_regret_return_separates_ledger(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $card = $customer->virtualCard;
        $card->update(['security_pin' => Hash::make('4321')]);

        $this->actingAs($customer)->post(route('card.pix.generate'), ['amount' => 300.00]);
        $charge = $card->recharges()->firstOrFail();
        $this->actingAs($customer)->post(route('webhooks.pix.simulate'), ['txid' => $charge->txid]);

        // Bloquear é imediato
        $this->actingAs($customer)->patch(route('card.toggle-block'))->assertSessionHas('success');
        $this->assertFalse($card->fresh()->is_active);

        // Desbloquear exige PIN quando configurado
        $this->actingAs($customer)->patch(route('card.toggle-block'), ['pin' => '0000'])
            ->assertSessionHasErrors('pin');
        $this->assertFalse($card->fresh()->is_active);

        $this->actingAs($customer)->patch(route('card.toggle-block'), ['pin' => '4321'])
            ->assertSessionHas('success');
        $this->assertTrue($card->fresh()->is_active);

        [$product] = $this->createProduct(['price' => 200.00, 'cashback_percent' => 10.0, 'stock' => 5]);
        $this->actingAs($customer)->post(route('orders.checkout'), [
            'shipping_address' => 'Rua Ledger, 10',
            'card_pin' => '4321',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $order = Order::firstOrFail();
        $this->actingAs($admin)->patch(route('admin.orders.deliver', $order));
        $this->actingAs($customer)->post(route('orders.return', $order));

        // Ledger separado para estorno de compra (+200) e estorno de cashback (-20)
        $this->assertDatabaseHas('card_transactions', [
            'virtual_card_id' => $card->id,
            'type' => CardTransaction::TYPE_REFUND,
            'amount' => 200.00,
        ]);
        $this->assertDatabaseHas('card_transactions', [
            'virtual_card_id' => $card->id,
            'type' => CardTransaction::TYPE_CASHBACK_REVERSAL,
            'amount' => -20.00,
        ]);

        $this->artisan('digitalcard:reconcile-ledgers')->assertExitCode(0);
    }

    public function test_admin_pages_handle_pix_null_receipt_correct_kpis_and_csv_sanitization(): void
    {
        $admin = User::factory()->admin()->create();
        $evilUser = User::factory()->create(['name' => '=CMD|calc!A0']);
        $evilUser->virtualCard->update([
            'balance' => 500.00,
            'security_pin' => Hash::make('1234'),
        ]);

        // Recarga PIX pendente (receipt_path = null) e aprovada com approved_amount distinto
        $evilUser->virtualCard->recharges()->create([
            'payment_method' => 'pix_dynamic',
            'txid' => 'DCPXNULLRECEIPTTEST0001',
            'pix_payload' => '000201',
            'amount' => 150.00,
            'receipt_path' => null,
            'status' => Recharge::STATUS_PENDING,
        ]);
        $evilUser->virtualCard->recharges()->create([
            'payment_method' => 'receipt',
            'amount' => 500.00,
            'approved_amount' => 120.00,
            'receipt_path' => 'receipts/fake.pdf',
            'receipt_hash' => hash('sha256', 'fake-approved'),
            'status' => Recharge::STATUS_APPROVED,
        ]);

        // Pedido cancelado de R$ 900 NÃO deve entrar no KPI de vendas líquidas
        Order::create([
            'user_id' => $evilUser->id,
            'total_amount' => 900.00,
            'cashback_total' => 0,
            'status' => Order::STATUS_CANCELLED,
            'shipping_address' => 'Rua Cancelada, 1',
        ]);
        Order::create([
            'user_id' => $evilUser->id,
            'total_amount' => 250.00,
            'cashback_total' => 10.00,
            'status' => Order::STATUS_DELIVERED,
            'shipping_address' => 'Rua Entregue, 2',
        ]);

        $dashboard = $this->actingAs($admin)->get(route('admin.dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('DCPXNULLRECEIPTTEST0001');
        $dashboard->assertSee('Cupons cadastrados');
        $dashboard->assertSee('Trilha de Auditoria Recente');

        $rechargesPage = $this->actingAs($admin)->get(route('admin.recharges.index'));
        $rechargesPage->assertOk();
        $rechargesPage->assertSee('R$ 120,00');

        $ordersPage = $this->actingAs($admin)->get(route('admin.orders.index'));
        $ordersPage->assertOk();
        $ordersPage->assertSee('R$ 250,00');
        $ordersPage->assertDontSee('R$ 1.150,00');

        $csvResponse = $this->actingAs($admin)->get(route('admin.reports.sales.csv'));
        $csvResponse->assertOk();
        $csvContent = $csvResponse->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csvContent);
        $this->assertStringContainsString("'=CMD|calc!A0", $csvContent);
    }

    public function test_commands_expire_pix_and_detect_ledger_mismatch(): void
    {
        $user = User::factory()->create();
        $card = $user->virtualCard;

        $expired = $card->recharges()->create([
            'payment_method' => 'pix_dynamic',
            'txid' => 'DCPXEXPIRED00000000000001',
            'pix_payload' => '000201',
            'amount' => 99.00,
            'expires_at' => now()->subMinutes(10),
            'status' => Recharge::STATUS_PENDING,
        ]);

        $this->artisan('digitalcard:expire-pix-charges')->assertExitCode(0);
        $this->assertSame(Recharge::STATUS_EXPIRED, $expired->fresh()->status);

        // Divergência artificial no saldo sem registro correspondente no ledger deve falhar reconciliação
        $card->update(['balance' => 42.00]);
        $this->artisan('digitalcard:reconcile-ledgers')->assertExitCode(1);
    }

    public function test_address_book_crud_and_role_based_access_control(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner)->post(route('addresses.store'), [
            'label' => 'Casa',
            'cep' => '57000-000',
            'number' => '100',
            'is_default' => true,
        ])->assertSessionHas('success');

        $address = Address::where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($other)->patch(route('addresses.default', $address))->assertForbidden();
        $this->actingAs($other)->delete(route('addresses.destroy', $address))->assertForbidden();

        $this->actingAs($owner)->delete(route('addresses.destroy', $address))->assertSessionHas('success');
        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);

        // RBAC granular: Logistics gerencia pedidos, mas não cria produtos nem aprova recargas
        $logistics = User::factory()->create();
        $logistics->forceFill(['role' => UserRole::Logistics->value, 'is_admin' => false])->save();

        [$product] = $this->createProduct();
        $order = Order::create([
            'user_id' => $owner->id,
            'total_amount' => 100.00,
            'cashback_total' => 5.00,
            'status' => Order::STATUS_REQUESTED,
            'shipping_address' => 'Rua RBAC, 1',
        ]);

        $this->actingAs($logistics)
            ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::SEPARATING->value])
            ->assertSessionHas('success');

        $this->actingAs($logistics)
            ->post(route('admin.products.store'), [
                'name' => 'Bloqueado',
                'category_id' => $product->category_id,
                'description' => 'Sem acesso',
                'price' => 10.00,
                'cashback_percent' => 1.0,
                'stock' => 1,
            ])
            ->assertForbidden();

        $recharge = $owner->virtualCard->recharges()->create([
            'amount' => 100.00,
            'receipt_path' => 'receipts/rbac.pdf',
            'receipt_hash' => hash('sha256', 'rbac-test'),
            'status' => Recharge::STATUS_PENDING,
        ]);

        $this->actingAs($logistics)
            ->patch(route('admin.recharges.approve', $recharge))
            ->assertForbidden();
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

        $product = Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Item Teste',
            'slug' => 'item-teste-'.uniqid(),
            'description' => 'Descrição item.',
            'price' => 100.00,
            'compare_at_price' => null,
            'cashback_percent' => 5.00,
            'stock' => 10,
            'is_active' => true,
        ], $attributes));

        return [$product, $category];
    }
}
