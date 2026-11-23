<?php

namespace Tests\Feature;

use App\Enums\LoyaltyTier;
use App\Http\Controllers\VirtualCardController;
use App\Models\CardTransaction;
use App\Models\Category;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\PriceAlert;
use App\Models\Product;
use App\Models\User;
use App\Notifications\ProductPriceDropNotification;
use App\Notifications\WalletTransferReceivedNotification;
use App\Services\CartPricing;
use App\Services\LoyaltyTierService;
use App\Services\WalletLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class FintechFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomerWithBalance(float $balance, string $pin = '1234', ?float $dailyLimit = null): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $card = $user->virtualCard()->firstOrFail();
        $card->update([
            'balance' => $balance,
            'security_pin' => Hash::make($pin),
            'daily_limit' => $dailyLimit,
            'is_active' => true,
        ]);

        if ($balance > 0) {
            CardTransaction::create([
                'virtual_card_id' => $card->id,
                'type' => CardTransaction::TYPE_RECHARGE,
                'amount' => $balance,
                'balance_after' => $balance,
                'description' => 'Saldo inicial de teste',
            ]);
        }

        return $user->fresh('virtualCard');
    }

    public function test_loyalty_tier_service_calculates_tiers_and_cart_pricing_applies_bonus_cashback_and_free_shipping(): void
    {
        $service = app(LoyaltyTierService::class);
        $pricing = app(CartPricing::class);

        $bronzeUser = $this->makeCustomerWithBalance(1000.00);
        $silverUser = $this->makeCustomerWithBalance(1000.00);

        Order::create([
            'user_id' => $silverUser->id,
            'subtotal_amount' => 3000.00,
            'discount_amount' => 0.00,
            'shipping_cost' => 0.00,
            'total_amount' => 3000.00,
            'cashback_total' => 100.00,
            'status' => Order::STATUS_DELIVERED,
            'shipping_address' => 'Rua Teste, 100 - Maceió/AL',
            'shipping_cep' => '57000-000',
            'delivered_at' => now()->subDays(2),
        ]);

        $silverSummary = $service->summaryFor($silverUser);
        $this->assertSame(LoyaltyTier::Silver, $silverSummary['tier']);
        $this->assertSame(1.0, $silverSummary['bonus_cashback_percent']);
        $this->assertSame(19900, $silverSummary['free_shipping_min_cents']);

        $this->assertSame(LoyaltyTier::Gold, LoyaltyTier::fromDeliveredSpendCents(850000));
        $this->assertSame(LoyaltyTier::Black, LoyaltyTier::fromDeliveredSpendCents(2200000));

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 200.00,
            'cashback_percent' => 5.00,
            'stock' => 10,
            'is_active' => true,
        ]);

        $rawCart = [$product->id => ['quantity' => 1]];

        // Para R$ 200: Bronze (< R$ 299) paga frete e ganha 5% = R$ 10,00
        $bronzeQuote = $pricing->calculate($rawCart, null, $bronzeUser, '01310-200');
        $this->assertGreaterThan(0, $bronzeQuote['shippingCost']);
        $this->assertSame(10.00, $bronzeQuote['cashbackTotal']);

        // Para R$ 200: Prata (>= R$ 199) ganha frete grátis e ganha 5% + 1% = 6% = R$ 12,00
        $silverQuote = $pricing->calculate($rawCart, null, $silverUser, '01310-200');
        $this->assertSame(0.00, $silverQuote['shippingCost']);
        $this->assertSame(12.00, $silverQuote['cashbackTotal']);
    }

    public function test_p2p_wallet_transfer_moves_funds_atomically_notifies_recipient_and_enforces_security(): void
    {
        Notification::fake();

        $sender = $this->makeCustomerWithBalance(1000.00, '1234', 400.00);
        $recipient = $this->makeCustomerWithBalance(100.00, '5678');
        $ledger = app(WalletLedgerService::class);

        RateLimiter::clear(VirtualCardController::pinThrottleKey($sender->id));

        // 1. Transferência válida de R$ 250,50
        $this->actingAs($sender)
            ->post(route('card.transfer'), [
                'recipient_email' => $recipient->email,
                'amount' => 250.50,
                'pin' => '1234',
                'note' => 'Reembolso almoço',
            ])
            ->assertRedirect(route('card.show'))
            ->assertSessionHas('success');

        $senderCard = $sender->virtualCard->fresh();
        $recipientCard = $recipient->virtualCard->fresh();

        $this->assertSame('749.50', $senderCard->balance);
        $this->assertSame('350.50', $recipientCard->balance);
        $this->assertTrue($ledger->reconcile($senderCard)['balanced']);
        $this->assertTrue($ledger->reconcile($recipientCard)['balanced']);

        Notification::assertSentTo($recipient, WalletTransferReceivedNotification::class);

        // 2. Excede limite diário (400 - 250.50 = 149.50 restante; tentativa de 200)
        $this->actingAs($sender)
            ->post(route('card.transfer'), [
                'recipient_email' => $recipient->email,
                'amount' => 200.00,
                'pin' => '1234',
            ])
            ->assertSessionHas('error');

        // 3. Transferência para si mesmo
        $this->actingAs($sender)
            ->post(route('card.transfer'), [
                'recipient_email' => $sender->email,
                'amount' => 10.00,
                'pin' => '1234',
            ])
            ->assertSessionHas('error');

        // 4. PIN incorreto
        $this->actingAs($sender)
            ->post(route('card.transfer'), [
                'recipient_email' => $recipient->email,
                'amount' => 10.00,
                'pin' => '9999',
            ])
            ->assertSessionHasErrors('pin');

        RateLimiter::clear(VirtualCardController::pinThrottleKey($sender->id));

        // 5. Cartão bloqueado não pode enviar
        $senderCard->update(['is_active' => false]);
        $this->actingAs($sender)
            ->post(route('card.transfer'), [
                'recipient_email' => $recipient->email,
                'amount' => 10.00,
                'pin' => '1234',
            ])
            ->assertSessionHas('error');

        $this->assertSame('749.50', $sender->virtualCard->fresh()->balance);
        $this->assertSame('350.50', $recipient->virtualCard->fresh()->balance);
    }

    public function test_gift_card_issue_and_redeem_lifecycle_prevents_double_redemption(): void
    {
        $issuer = $this->makeCustomerWithBalance(800.00, '1234', 500.00);
        $redeemer = $this->makeCustomerWithBalance(50.00, '4321');
        $ledger = app(WalletLedgerService::class);

        RateLimiter::clear(VirtualCardController::pinThrottleKey($issuer->id));

        // Excede limite diário (500)
        $this->actingAs($issuer)
            ->post(route('gift-cards.store'), [
                'amount' => 600.00,
                'pin' => '1234',
            ])
            ->assertSessionHas('error');

        // Emissão válida de R$ 250,00
        $this->actingAs($issuer)
            ->post(route('gift-cards.store'), [
                'amount' => 250.00,
                'pin' => '1234',
                'recipient_name' => 'Maria Silva',
                'recipient_email' => $redeemer->email,
                'message' => 'Boas compras!',
            ])
            ->assertRedirect(route('card.show'))
            ->assertSessionHas('success');

        $giftCard = GiftCard::firstOrFail();
        $this->assertStringStartsWith('DCGC-', $giftCard->code);
        $this->assertSame(GiftCard::STATUS_ACTIVE, $giftCard->status);
        $this->assertSame('550.00', $issuer->virtualCard->fresh()->balance);

        // Resgate pelo destinatário
        $this->actingAs($redeemer)
            ->post(route('gift-cards.redeem'), [
                'code' => strtolower($giftCard->code),
            ])
            ->assertRedirect(route('card.show'))
            ->assertSessionHas('success');

        $giftCard->refresh();
        $this->assertSame(GiftCard::STATUS_REDEEMED, $giftCard->status);
        $this->assertSame($redeemer->id, $giftCard->redeemer_user_id);
        $this->assertSame('300.00', $redeemer->virtualCard->fresh()->balance);

        // Impede resgate duplicado
        $this->actingAs($issuer)
            ->post(route('gift-cards.redeem'), [
                'code' => $giftCard->code,
            ])
            ->assertSessionHas('error');

        $this->assertTrue($ledger->reconcile($issuer->virtualCard->fresh())['balanced']);
        $this->assertTrue($ledger->reconcile($redeemer->virtualCard->fresh())['balanced']);
    }

    public function test_price_drop_alert_triggers_notification_only_on_eligible_price_drop_and_can_be_deleted(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);
        $customer = $this->makeCustomerWithBalance(500.00);
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Monitor LG OLED',
            'slug' => 'monitor-lg-oled-test',
            'price' => 1500.00,
            'compare_at_price' => 1800.00,
            'cashback_percent' => 5.00,
            'stock' => 10,
            'is_active' => true,
        ]);

        // Cria alerta com preço-alvo de R$ 1.250,00
        $this->actingAs($customer)
            ->post(route('price-alerts.store', $product->slug), [
                'target_price' => 1250.00,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $alert = PriceAlert::where('user_id', $customer->id)->where('product_id', $product->id)->firstOrFail();

        // Queda parcial para R$ 1.299 (acima do alvo) não notifica
        $this->actingAs($admin)
            ->patch(route('admin.products.update', $product), [
                'name' => $product->name,
                'category_id' => $product->category_id,
                'price' => 1299.00,
                'compare_at_price' => 1800.00,
                'cashback_percent' => 5.00,
                'stock' => 10,
            ])
            ->assertRedirect();

        Notification::assertNothingSent();

        // Queda para R$ 1.199 (<= R$ 1.250) dispara notificação
        $this->actingAs($admin)
            ->patch(route('admin.products.update', $product), [
                'name' => $product->name,
                'category_id' => $product->category_id,
                'price' => 1199.00,
                'compare_at_price' => 1800.00,
                'cashback_percent' => 5.00,
                'stock' => 10,
            ])
            ->assertRedirect();

        Notification::assertSentToTimes($customer, ProductPriceDropNotification::class, 1);

        // Salvamento no mesmo preço não gera spam
        $this->actingAs($admin)
            ->patch(route('admin.products.update', $product), [
                'name' => $product->name,
                'category_id' => $product->category_id,
                'price' => 1199.00,
                'compare_at_price' => 1800.00,
                'cashback_percent' => 5.00,
                'stock' => 10,
            ])
            ->assertRedirect();

        Notification::assertSentToTimes($customer, ProductPriceDropNotification::class, 1);

        // Cliente remove o alerta
        $this->actingAs($customer)
            ->delete(route('price-alerts.destroy', $alert))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('price_alerts', ['id' => $alert->id]);
    }
}
