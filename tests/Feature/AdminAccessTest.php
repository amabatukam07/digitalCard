<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_request_with_database_cache_store_returns_200_without_incomplete_class_error(): void
    {
        $this->seed(CatalogSeeder::class);

        // Força o driver 'database' (onde serializable_classes => false está ativo)
        config(['cache.default' => 'database']);
        Cache::store('database')->forget('nav_categories_tree');

        $first = $this->get(route('catalog.index'));
        $first->assertOk();
        $first->assertSee('Copa, Água &amp; Bebidas', false);

        $second = $this->get(route('catalog.index'));
        $second->assertOk();
        $second->assertSee('Copa, Água &amp; Bebidas', false);
        $second->assertSee('Escritório &amp; Ergonomia', false);
    }

    public function test_mass_assignment_cannot_escalate_is_admin_or_role_and_security_headers_are_present(): void
    {
        $user = new User([
            'name' => 'Tentativa Admin',
            'email' => 'hacker@example.com',
            'password' => 'password1234',
            'is_admin' => true,
            'role' => UserRole::Admin->value,
        ]);
        $user->save();

        $fresh = $user->fresh();
        $this->assertFalse((bool) $fresh->is_admin);
        $this->assertSame(UserRole::CompanyRequester->value, $fresh->role);

        $response = $this->get('/');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_no_fabricated_ratings_or_fake_discounts_and_sitemap_works(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::create(['name' => 'Limpeza Corporativa', 'slug' => 'limpeza-corporativa']);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Detergente Industrial 5L',
            'category_id' => $category->id,
            'unit' => 'galão',
            'presentation' => 'Galão 5 L',
            'description' => 'Descrição real do produto B2B.',
            'cost_price' => 25.00,
            'inbound_freight' => 2.00,
            'other_costs' => 0.00,
            'markup_percent' => 35.0,
            'das_rate' => 4.0,
            'stock' => 20,
            'min_stock' => 5,
        ])->assertSessionHas('success');

        $product = Product::where('slug', 'detergente-industrial-5l')->firstOrFail();

        $this->assertNull($product->compare_at_price);
        $this->assertEquals(0, $product->discount_percent);
        $this->assertEquals(0.0, $product->rating);
        $this->assertEquals(0, $product->reviews_count);
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'sale_price' => (float) $product->price,
        ]);

        $sitemap = $this->get(route('sitemap'));
        $sitemap->assertOk();
        $sitemap->assertSee(route('catalog.product', $product->slug), false);
    }

    public function test_dedicated_admin_layout_and_seo_meta(): void
    {
        $this->seed(CatalogSeeder::class);

        $admin = User::factory()->admin()->create();
        $adminResp = $this->actingAs($admin)->get(route('admin.dashboard'));
        $adminResp->assertOk();
        $adminResp->assertSee('Backoffice NexoSupri');
        $adminResp->assertSee('Voltar ao Portal');
        $adminResp->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $adminResp->assertDontSee('SALDO DIGITALCARD');
    }

    public function test_admin_backoffice_supports_ihc_tabs_accessibility_and_module_filters(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@nexosupri.com.br')->firstOrFail();

        $dashboard = $this->actingAs($admin)->get(route('admin.dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('Pular para o conteúdo principal');
        $dashboard->assertSee('aria-current="page"', false);
        $dashboard->assertSee('role="tablist"', false);
        $dashboard->assertSee('Visão Executiva');
        $dashboard->assertSee('Fila Operacional');
        $dashboard->assertSee('Catálogo &amp; Preços', false);
        $dashboard->assertSee('Simulador DAS &amp; Governança', false);

        $lowStockCatalog = $this->actingAs($admin)->get(route('admin.dashboard', [
            'tab' => 'catalogo',
            'low_stock' => '1',
        ]));
        $lowStockCatalog->assertOk();
        $this->assertSame('catalogo', $lowStockCatalog->viewData('activeTab'));

        $this->actingAs($admin)->get(route('admin.companies.index', ['status' => 'low_balance']))->assertOk();
        $this->actingAs($admin)->get(route('admin.payments.index', ['q' => 'Alfa']))->assertOk();

        $operableOrder = Order::latest('id')->firstOrFail();
        $operableOrder->update(['status' => Order::STATUS_REQUESTED]);

        $orders = $this->actingAs($admin)->get(route('admin.orders.index'));
        $orders->assertOk();
        $orders->assertSee('Pedidos &amp; Entregas', false);
        $orders->assertDontSee('&amp;amp;', false);
        $orders->assertSee(route('admin.orders.status', $operableOrder), false);
        $orders->assertSee(route('admin.orders.cancel', $operableOrder), false);

        $suppliers = $this->actingAs($admin)->get(route('admin.suppliers.index'));
        $suppliers->assertOk();
        $suppliers->assertSee(route('admin.suppliers.link-product'), false);
    }
}
