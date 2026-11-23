<?php

namespace Tests\Feature;

use App\Models\CardTransaction;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PriceAlert;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductQuestion;
use App\Models\ProductReview;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Models\Wishlist;
use App\Notifications\ProductQuestionAnsweredNotification;
use App\Services\WalletLedgerService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class Wave2CommerceAndFintechTest extends TestCase
{
    use RefreshDatabase;

    private function makeVerifiedCustomer(float $balance = 1000.00): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $card = $user->virtualCard()->firstOrFail();
        $card->update([
            'balance' => $balance,
            'security_pin' => Hash::make('1234'),
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

    public function test_curated_bundles_page_renders_and_adds_all_bundle_items_to_cart(): void
    {
        $this->seed(CatalogSeeder::class);

        Coupon::create([
            'code' => 'SETUP15',
            'label' => '15% OFF na linha Escritório & Setup acima de R$ 1.200',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 15.00,
            'min_total' => 1200.00,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonths(6),
            'max_uses' => 250,
            'per_user_limit' => 2,
            'is_active' => true,
        ]);

        $this->assertSame(4, ProductBundle::where('is_active', true)->count());

        $this->get(route('bundles.index'))
            ->assertOk()
            ->assertSee('Kits & Setups Prontos')
            ->assertSee('Kit Home Office Executivo Completo');

        $bundle = ProductBundle::where('slug', 'kit-home-office-executivo')->with('items')->firstOrFail();

        $this->post(route('bundles.add-to-cart', $bundle->slug))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('success');

        $cart = session('cart', []);
        $this->assertCount($bundle->items->count(), $cart);
        $this->assertSame('SETUP15', session('cart_coupon'));
    }

    public function test_product_qa_submission_admin_answer_notification_and_visibility(): void
    {
        Notification::fake();

        $customer = $this->makeVerifiedCustomer();
        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Teclado Keychron Q1',
            'slug' => 'teclado-keychron-qa-test',
            'is_active' => true,
            'stock' => 10,
        ]);

        $this->actingAs($customer)
            ->post(route('questions.store', $product->slug), [
                'question' => 'Este teclado funciona nativamente com macOS?',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $question = ProductQuestion::where('product_id', $product->id)->firstOrFail();
        $this->assertNull($question->answer);

        $this->actingAs($admin)
            ->patch(route('admin.questions.answer', $question), [
                'answer' => 'Sim! Possui chave física para alternar entre macOS e Windows.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $question->refresh();
        $this->assertSame('Sim! Possui chave física para alternar entre macOS e Windows.', $question->answer);
        $this->assertNotNull($question->answered_at);

        Notification::assertSentTo($customer, ProductQuestionAnsweredNotification::class);

        $this->get(route('catalog.product', $product->slug))
            ->assertOk()
            ->assertSee('Este teclado funciona nativamente com macOS?')
            ->assertSee('Sim! Possui chave física para alternar entre macOS e Windows.');
    }

    public function test_savings_goal_cofrinho_deposit_withdraw_delete_and_account_guard(): void
    {
        $user = $this->makeVerifiedCustomer(1200.00);
        $ledger = app(WalletLedgerService::class);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 500.00,
            'stock' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('goals.store'), [
                'name' => 'Meu Novo Setup',
                'target_amount' => 500.00,
                'product_id' => $product->id,
            ])
            ->assertRedirect(route('card.show'))
            ->assertSessionHas('success');

        $goal = SavingsGoal::where('user_id', $user->id)->firstOrFail();

        // Depósito de R$ 500 atinge 100% e debita cartão
        $this->actingAs($user)
            ->post(route('goals.deposit', $goal), ['amount' => 500.00])
            ->assertRedirect(route('card.show'))
            ->assertSessionHas('success');

        $goal->refresh();
        $card = $user->virtualCard->fresh();
        $this->assertSame('500.00', $goal->current_amount);
        $this->assertNotNull($goal->completed_at);
        $this->assertSame('700.00', $card->balance);
        $this->assertTrue($ledger->reconcile($card)['balanced']);

        // Resgate parcial de R$ 200 devolve ao saldo e limpa completed_at
        $this->actingAs($user)
            ->post(route('goals.withdraw', $goal), ['amount' => 200.00])
            ->assertRedirect(route('card.show'));

        $goal->refresh();
        $card->refresh();
        $this->assertSame('300.00', $goal->current_amount);
        $this->assertNull($goal->completed_at);
        $this->assertSame('900.00', $card->balance);

        // Se zerar cartão e manter R$ 300 no cofrinho, exclusão da conta é bloqueada
        $card->update(['balance' => 0.00]);
        $this->actingAs($user->fresh())
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'goals');
        $card->update(['balance' => 900.00]);

        // Excluir meta estorna os R$ 300 restantes de volta para a carteira
        $this->actingAs($user->fresh())
            ->delete(route('goals.destroy', $goal))
            ->assertRedirect(route('card.show'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('savings_goals', ['id' => $goal->id]);
        $card->refresh();
        $this->assertSame('1200.00', $card->balance);
        $this->assertTrue($ledger->reconcile($card)['balanced']);
    }

    public function test_one_click_reorder_and_wishlist_bulk_move_and_profile_price_alerts(): void
    {
        $user = $this->makeVerifiedCustomer(500.00);
        $category = Category::factory()->create();

        $available = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Microfone Estúdio',
            'price' => 400.00,
            'stock' => 4,
            'is_active' => true,
        ]);

        $outOfStock = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Câmera Esgotada',
            'price' => 900.00,
            'stock' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'subtotal_amount' => 1300.00,
            'discount_amount' => 0.00,
            'shipping_cost' => 0.00,
            'total_amount' => 1300.00,
            'cashback_total' => 50.00,
            'status' => Order::STATUS_DELIVERED,
            'shipping_address' => 'Rua Teste, 10',
            'shipping_cep' => '01310-200',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $available->id,
            'product_name' => $available->name,
            'product_sku' => $available->slug,
            'quantity' => 2,
            'unit_price' => 400.00,
            'cashback_percent' => 5.00,
            'discount_allocated' => 0.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $outOfStock->id,
            'product_name' => $outOfStock->name,
            'product_sku' => $outOfStock->slug,
            'quantity' => 1,
            'unit_price' => 900.00,
            'cashback_percent' => 5.00,
            'discount_allocated' => 0.00,
        ]);

        // Reorder readiciona apenas o item disponível (2 un.)
        $this->actingAs($user)
            ->post(route('orders.reorder', $order))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('success');

        $cart = session('cart', []);
        $this->assertArrayHasKey($available->id, $cart);
        $this->assertArrayNotHasKey($outOfStock->id, $cart);
        $this->assertSame(2, $cart[$available->id]['quantity']);

        // Favoritos em massa -> move para carrinho
        session()->forget('cart');
        Wishlist::create(['user_id' => $user->id, 'product_id' => $available->id]);

        $this->actingAs($user)
            ->post(route('wishlist.move-to-cart'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('success');

        $this->assertArrayHasKey($available->id, session('cart', []));

        // Perfil exibe alertas de preço ativos
        PriceAlert::create([
            'user_id' => $user->id,
            'product_id' => $available->id,
            'target_price' => 350.00,
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Meus Alertas de Preço')
            ->assertSee('Microfone Estúdio');
    }

    public function test_admin_dashboard_intelligence_panels_and_api_v1_sorting(): void
    {
        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);
        $customer = $this->makeVerifiedCustomer(1000.00);
        $category = Category::factory()->create(['name' => 'Periféricos API', 'slug' => 'perifericos-api']);

        $topCb = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Monitor Top Cashback',
            'price' => 1200.00,
            'cashback_percent' => 14.00,
            'stock' => 2,
            'is_active' => true,
        ]);

        $lowRating = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Webcam Nota Baixa',
            'price' => 300.00,
            'cashback_percent' => 3.00,
            'stock' => 15,
            'is_active' => true,
        ]);

        ProductReview::create([
            'product_id' => $topCb->id,
            'user_id' => $customer->id,
            'rating' => 5,
            'comment' => 'Excelente!',
        ]);

        ProductReview::create([
            'product_id' => $lowRating->id,
            'user_id' => $customer->id,
            'rating' => 2,
            'comment' => 'Mediana.',
        ]);

        ProductQuestion::create([
            'product_id' => $topCb->id,
            'user_id' => $customer->id,
            'question' => 'Tem cabo USB-C incluso na caixa?',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Cofrinhos reservados')
            ->assertSee('Alertas de estoque crítico')
            ->assertSee('Monitor Top Cashback')
            ->assertSee('Tem cabo USB-C incluso na caixa?');

        $apiRes = $this->getJson('/api/v1/products?sort=maior_cashback&min_rating=4');
        $apiRes->assertOk();
        $names = collect($apiRes->json('data'))->pluck('name')->all();
        $this->assertContains('Monitor Top Cashback', $names);
        $this->assertNotContains('Webcam Nota Baixa', $names);
    }
}
