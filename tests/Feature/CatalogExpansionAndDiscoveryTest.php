<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogExpansionAndDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeder_populates_9_departments_23_subcategories_and_90_products_with_studio_images(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertSame(9, Category::whereNull('parent_id')->count());
        $this->assertSame(23, Category::whereNotNull('parent_id')->count());

        $products = Product::where('is_active', true)->get();
        $this->assertCount(90, $products);

        foreach ($products as $product) {
            $fullPath = public_path("images/products/{$product->slug}.webp");
            $thumbPath = public_path("images/products/{$product->slug}-400.webp");

            $this->assertFileExists($fullPath, "Missing full WebP image for {$product->slug}");
            $this->assertFileExists($thumbPath, "Missing 400w WebP thumbnail for {$product->slug}");
            $this->assertGreaterThan(1024, filesize($fullPath), "Empty full WebP for {$product->slug}");
            $this->assertGreaterThan(1024, filesize($thumbPath), "Empty 400w WebP for {$product->slug}");
        }
    }

    public function test_home_storefront_renders_expanded_sections_and_sorting_options(): void
    {
        $this->seed(CatalogSeeder::class);

        $reviewer = User::factory()->create();
        $aeron = Product::where('slug', 'cadeira-ergonomica-herman-miller-aeron')->firstOrFail();
        $mouse = Product::where('slug', 'mouse-ergonomico-logitech-mx-master-3s')->firstOrFail();

        ProductReview::create([
            'product_id' => $aeron->id,
            'user_id' => $reviewer->id,
            'rating' => 5,
            'comment' => 'Referência ergonômica excelente.',
        ]);

        ProductReview::create([
            'product_id' => $mouse->id,
            'user_id' => $reviewer->id,
            'rating' => 3,
            'comment' => 'Bom mouse.',
        ]);

        $homeResponse = $this->get(route('catalog.index'));
        $homeResponse->assertOk();
        $homeResponse->assertSee('Explore por categoria');
        $homeResponse->assertSee('Cashback turbinado');
        $homeResponse->assertSee('Cupons ativos para seu carrinho');
        $homeResponse->assertSee('Escritório & Ergonomia');
        $homeResponse->assertSee('Áudio Pro & Criação');
        $homeResponse->assertSee('Mobilidade & Energia');
        $homeResponse->assertSee('Saúde & Bem-Estar Tech');

        $topCashbackProduct = Product::where('is_active', true)->orderByDesc('cashback_percent')->orderBy('id')->firstOrFail();
        $this->get(route('catalog.index', ['sort' => 'maior_cashback']))
            ->assertOk()
            ->assertSee($topCashbackProduct->name);

        $this->get(route('catalog.index', ['sort' => 'melhor_avaliados', 'min_rating' => 4]))
            ->assertOk()
            ->assertSee($aeron->name)
            ->assertDontSee($mouse->name);
    }

    public function test_live_search_suggestions_returns_json_matches_and_ignores_short_queries(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->getJson(route('catalog.suggestions', ['q' => 'a']))
            ->assertOk()
            ->assertExactJson([
                'products' => [],
                'categories' => [],
            ]);

        $response = $this->getJson(route('catalog.suggestions', ['q' => 'Cadeira']));
        $response->assertOk();
        $response->assertJsonPath('categories.0.name', 'Cadeiras Ergonômicas');

        $productNames = collect($response->json('products'))->pluck('name')->all();
        $this->assertNotEmpty($productNames);
        $this->assertStringContainsString('Cadeira', $productNames[0]);
    }

    public function test_product_comparator_limits_to_four_items_and_renders_best_value_highlights(): void
    {
        $category = Category::factory()->create(['name' => 'Periféricos', 'slug' => 'perifericos-comp']);

        $products = [];
        foreach ([100, 250, 400, 550, 700] as $idx => $price) {
            $products[] = Product::factory()->create([
                'category_id' => $category->id,
                'name' => 'Monitor Pro '.($idx + 1),
                'slug' => 'monitor-pro-'.($idx + 1),
                'price' => $price,
                'cashback_percent' => $idx === 2 ? 12.0 : 4.0,
                'stock' => 10,
                'is_active' => true,
            ]);
        }

        for ($i = 0; $i < 4; $i++) {
            $this->post(route('compare.toggle', $products[$i]))
                ->assertRedirect()
                ->assertSessionHas('success');
        }

        $this->assertCount(4, session('compare_product_ids'));

        $this->post(route('compare.toggle', $products[4]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertCount(4, session('compare_product_ids'));

        $comparePage = $this->get(route('compare.index'));
        $comparePage->assertOk();
        $comparePage->assertSee('Melhor preço');
        $comparePage->assertSee('Maior cashback');
        $comparePage->assertSee('Monitor Pro 1');
        $comparePage->assertSee('Monitor Pro 4');
        $comparePage->assertDontSee('Monitor Pro 5');

        $this->delete(route('compare.remove', $products[0]))->assertRedirect();
        $this->assertCount(3, session('compare_product_ids'));

        $this->delete(route('compare.clear'))->assertRedirect();
        $this->assertEmpty(session('compare_product_ids'));
    }

    public function test_recently_viewed_tracks_fifo_max_8_and_renders_excluding_current_product(): void
    {
        $category = Category::factory()->create(['name' => 'Acessórios', 'slug' => 'acessorios-rec']);
        $items = [];

        for ($i = 1; $i <= 9; $i++) {
            $items[$i] = Product::factory()->create([
                'category_id' => $category->id,
                'name' => "Item Recente {$i}",
                'slug' => "item-recente-{$i}",
                'stock' => 5,
                'is_active' => true,
            ]);

            $this->get(route('catalog.product', $items[$i]->slug))->assertOk();
        }

        $recentIds = session('recently_viewed');
        $this->assertCount(8, $recentIds);
        $this->assertSame($items[9]->id, $recentIds[0]);
        $this->assertNotContains($items[1]->id, $recentIds);

        $detailResponse = $this->get(route('catalog.product', $items[9]->slug));
        $detailResponse->assertOk();
        $detailResponse->assertSee('Vistos recentemente');
        $detailResponse->assertSee('Item Recente 8');

        $homeResponse = $this->get(route('catalog.index'));
        $homeResponse->assertOk();
        $homeResponse->assertSee('Vistos recentemente por você');
        $homeResponse->assertSee('Item Recente 9');
    }
}
