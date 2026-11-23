<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CardTransaction;
use App\Models\Category;
use App\Models\Product;
use App\Models\Recharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnterpriseFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_rotate_cvv_with_valid_pin_and_export_sanitized_csv_statement(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $card = $owner->virtualCard;
        $card->update([
            'cvv' => '111',
            'security_pin' => Hash::make('4321'),
            'balance' => 100.00,
        ]);

        // Falha com PIN incorreto
        $this->actingAs($owner)
            ->post(route('card.rotate-cvv'), ['pin' => '0000'])
            ->assertSessionHasErrors('pin');
        $this->assertSame('111', $card->fresh()->cvv);

        // Sucesso com PIN válido
        $this->actingAs($owner)
            ->post(route('card.rotate-cvv'), ['pin' => '4321'])
            ->assertSessionHas('success');
        $this->assertNotSame('111', $card->fresh()->cvv);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'card.cvv_rotated',
        ]);

        // Extrato CSV com sanitização anti-Formula Injection e isolado por usuário
        $card->transactions()->create([
            'type' => CardTransaction::TYPE_RECHARGE,
            'amount' => 100.00,
            'balance_after' => 100.00,
            'description' => '=HYPERLINK("http://evil","Click")',
        ]);
        $other->virtualCard->transactions()->create([
            'type' => CardTransaction::TYPE_RECHARGE,
            'amount' => 999.00,
            'balance_after' => 999.00,
            'description' => 'SEGREDO_OUTRO_USUARIO',
        ]);

        $csvResponse = $this->actingAs($owner)->get(route('card.statement.csv'));
        $csvResponse->assertOk();
        $csv = $csvResponse->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=HYPERLINK(", $csv);
        $this->assertStringNotContainsString('SEGREDO_OUTRO_USUARIO', $csv);
    }

    public function test_database_notifications_are_recorded_for_orders_and_recharges_and_marked_as_read(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $customer->virtualCard->update([
            'balance' => 500.00,
            'security_pin' => Hash::make('1234'),
        ]);

        $category = Category::create(['name' => 'Notificações', 'slug' => 'notif-cat']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Teclado Mecânico',
            'slug' => 'teclado-mecanico-notif',
            'description' => 'Teclado',
            'price' => 200.00,
            'cashback_percent' => 5.0,
            'stock' => 5,
            'is_active' => true,
        ]);

        // Checkout grava OrderStatusUpdatedNotification em database
        $this->actingAs($customer)->post(route('orders.checkout'), [
            'shipping_address' => 'Rua Notif, 10',
            'card_pin' => '1234',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        // Aprovação de recarga grava RechargeReviewedNotification em database
        $recharge = $customer->virtualCard->recharges()->create([
            'amount' => 150.00,
            'receipt_path' => 'receipts/notif.pdf',
            'receipt_hash' => hash('sha256', 'notif-receipt'),
            'status' => Recharge::STATUS_PENDING,
        ]);
        $this->actingAs($admin)->patch(route('admin.recharges.approve', $recharge));

        $this->assertSame(2, $customer->fresh()->unreadNotifications()->count());

        $this->actingAs($customer)
            ->post(route('notifications.read-all'))
            ->assertSessionHas('success');

        $this->assertSame(0, $customer->fresh()->unreadNotifications()->count());
    }

    public function test_admin_can_manage_user_roles_but_cannot_self_demote_and_non_admin_is_blocked(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $finance = User::factory()->create();
        $finance->forceFill(['role' => UserRole::Finance->value, 'is_admin' => false])->save();

        // Auto-alteração de papel bloqueada
        $this->actingAs($admin)
            ->patch(route('admin.users.role', $admin), ['role' => UserRole::Customer->value])
            ->assertForbidden();

        // Usuário Finance não pode gerenciar papéis RBAC (apenas Admin geral)
        $this->actingAs($finance)
            ->patch(route('admin.users.role', $target), ['role' => UserRole::Logistics->value])
            ->assertForbidden();

        // Admin promove usuário para Logística
        $this->actingAs($admin)
            ->patch(route('admin.users.role', $target), ['role' => UserRole::Logistics->value])
            ->assertSessionHas('success');

        $this->assertSame(UserRole::Logistics, $target->fresh()->roleEnum());
        $this->assertFalse((bool) $target->fresh()->is_admin);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.role_updated',
            'auditable_id' => $target->id,
        ]);
    }

    public function test_observability_request_id_healthcheck_and_catalog_api_v1_work(): void
    {
        $category = Category::create(['name' => 'API Dept', 'slug' => 'api-dept']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Monitor 4K',
            'slug' => 'monitor-4k-api',
            'description' => 'Tela 4K IPS',
            'price' => 1800.00,
            'cashback_percent' => 4.0,
            'stock' => 7,
            'is_active' => true,
        ]);

        // Healthcheck + Request ID
        $health = $this->withHeaders(['X-Request-Id' => 'req-trace-12345'])
            ->getJson(route('health'));

        $health->assertOk()
            ->assertHeader('X-Request-Id', 'req-trace-12345')
            ->assertJson([
                'status' => 'ok',
                'checks' => [
                    'database' => true,
                    'cache' => true,
                    'storage' => true,
                ],
            ]);

        // API REST v1
        $list = $this->getJson('/api/v1/products?q=Monitor');
        $list->assertOk()
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('data.0.slug', 'monitor-4k-api')
            ->assertJsonPath('data.0.price', 1800);

        $detail = $this->getJson('/api/v1/products/'.$product->slug);
        $detail->assertOk()
            ->assertJsonPath('data.name', 'Monitor 4K');

        $categories = $this->getJson('/api/v1/categories');
        $categories->assertOk()
            ->assertJsonPath('data.0.slug', 'api-dept');

        $shipping = $this->getJson('/api/v1/shipping/quote?cep=57000-000&total_cents=15000');
        $shipping->assertOk()
            ->assertJsonPath('quote.uf', 'AL')
            ->assertJsonPath('quote.is_free', false);
    }

    public function test_pix_hmac_webhook_validates_signature_timestamp_and_event_idempotency(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('card.pix.generate'), ['amount' => 350.00]);
        $charge = $user->virtualCard->recharges()->firstOrFail();

        $payload = json_encode([
            'event_id' => 'evt-pix-0001',
            'txid' => $charge->txid,
        ], JSON_THROW_ON_ERROR);

        $secret = (string) config('digitalcard.pix.webhook_secret');
        $validTimestamp = (string) now()->timestamp;
        $validSignature = hash_hmac('sha256', $validTimestamp.'.'.$payload, $secret);

        // Assinatura inválida retorna 401
        $this->call('POST', '/api/v1/webhooks/pix', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_TIMESTAMP' => $validTimestamp,
            'HTTP_X_WEBHOOK_SIGNATURE' => 'assinatura-invalida',
        ], $payload)->assertStatus(401);

        // Timestamp expirado retorna 401
        $oldTimestamp = (string) (now()->timestamp - 900);
        $oldSignature = hash_hmac('sha256', $oldTimestamp.'.'.$payload, $secret);
        $this->call('POST', '/api/v1/webhooks/pix', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_TIMESTAMP' => $oldTimestamp,
            'HTTP_X_WEBHOOK_SIGNATURE' => $oldSignature,
        ], $payload)->assertStatus(401);

        // Assinatura válida credita saldo uma única vez
        $this->call('POST', '/api/v1/webhooks/pix', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_TIMESTAMP' => $validTimestamp,
            'HTTP_X_WEBHOOK_SIGNATURE' => $validSignature,
        ], $payload)
            ->assertOk()
            ->assertJson(['status' => 'processed', 'txid' => $charge->txid]);

        $this->assertEquals(350.00, (float) $user->virtualCard->fresh()->balance);

        // Reenvio idempotente do mesmo event_id retorna duplicate_ignored sem duplicar crédito
        $this->call('POST', '/api/v1/webhooks/pix', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_TIMESTAMP' => $validTimestamp,
            'HTTP_X_WEBHOOK_SIGNATURE' => $validSignature,
        ], $payload)
            ->assertOk()
            ->assertJson(['status' => 'duplicate_ignored']);

        $this->assertEquals(350.00, (float) $user->virtualCard->fresh()->balance);
        $this->assertSame(1, $user->virtualCard->transactions()->count());
    }
}
