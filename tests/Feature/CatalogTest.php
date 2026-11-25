<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_page_renders_both_original_products_and_nexosupri_supplies(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertSame(6, Category::whereNull('parent_id')->count());
        $this->assertSame(20, Category::count());
        $this->assertGreaterThanOrEqual(110, Product::count());

        $response = $this->get(route('catalog.index'));

        $response->assertStatus(200);
        $response->assertSee('Escritório &amp; Ergonomia', false);
        $response->assertSee('Copa, Água &amp; Bebidas', false);
        $response->assertSee('Limpeza &amp; Higiene', false);
        $response->assertSee('Informática &amp; Áudio Pro', false);
        $response->assertSee('Eletrônicos &amp; Câmeras', false);
        $response->assertSee('Games &amp; Consoles', false);
        $response->assertDontSee('Insumos por categoria');
    }

    public function test_catalog_can_filter_by_category_original_and_new(): void
    {
        $this->seed(CatalogSeeder::class);

        $office = $this->get(route('catalog.category', 'escritorio'));
        $office->assertStatus(200);
        $office->assertSee('Papel A4');
        $office->assertDontSee('PlayStation 5 Slim');

        $consoles = $this->get(route('catalog.category', 'consoles'));
        $consoles->assertStatus(200);
        $consoles->assertSee('PlayStation 5 Slim');
        $consoles->assertDontSee('Detergente 500 ml');
    }

    public function test_catalog_search_filters_products_by_keyword(): void
    {
        $this->seed(CatalogSeeder::class);

        $papel = $this->get(route('catalog.index', ['q' => 'Papel A4']));
        $papel->assertStatus(200);
        $papel->assertSee('Papel A4');
        $papel->assertDontSee('PlayStation 5 Slim');

        $ps5 = $this->get(route('catalog.index', ['q' => 'PlayStation']));
        $ps5->assertStatus(200);
        $ps5->assertSee('PlayStation 5 Slim');
        $ps5->assertDontSee('Café 500 g');
    }

    public function test_catalog_can_filter_by_price_range(): void
    {
        $this->seed(CatalogSeeder::class);

        $response = $this->get(route('catalog.index', [
            'min_price' => 300,
            'max_price' => 360,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Papel A4 Caixa Fechada (5.000 fls)');
        $response->assertDontSee('Copo descartável 180 ml');
    }

    public function test_catalog_can_sort_by_lowest_and_highest_price(): void
    {
        $this->seed(CatalogSeeder::class);

        $lowest = $this->get(route('catalog.index', ['sort' => 'menor_preco']));
        $lowest->assertStatus(200);
        $lowest->assertSeeInOrder(['Papel toalha', 'Desinfetante 2 L', 'Copo descartável 180 ml']);

        $highest = $this->get(route('catalog.index', ['sort' => 'maior_preco']));
        $highest->assertStatus(200);
        $highest->assertSeeInOrder([
            'Câmera Mirrorless Full-Frame Sony Alpha A7 IV',
            'Apple iPhone 16 Pro Max',
        ]);
    }

    public function test_every_seeded_product_has_at_least_three_gallery_images_and_carousel_renders(): void
    {
        $this->seed(CatalogSeeder::class);

        $products = Product::with('images')->get();
        $this->assertGreaterThanOrEqual(110, $products->count());

        foreach ($products as $product) {
            $this->assertGreaterThanOrEqual(3, $product->images->count(), "Produto {$product->slug} sem 3 imagens.");
            $paths = [];
            $hashes = [];
            foreach ($product->images as $img) {
                $this->assertStringNotContainsString('.svg', strtolower($img->image_path), "Produto {$product->slug} ainda aponta para SVG.");
                $fullPath = public_path(ltrim($img->image_path, '/'));
                $this->assertFileExists($fullPath);
                $this->assertGreaterThanOrEqual(5000, filesize($fullPath), "Imagem muito pequena em {$img->image_path}");
                $paths[] = $img->image_path;
                $hashes[] = hash_file('sha256', $fullPath);
            }
            $this->assertCount($product->images->count(), array_unique($paths), "Caminhos repetidos em {$product->slug}");
            $this->assertCount($product->images->count(), array_unique($hashes), "Arquivos de foto duplicados em {$product->slug}");
        }

        // Card mini-carrossel na home
        $home = $this->get(route('catalog.index'));
        $home->assertOk();
        $home->assertSee('carousel-prev-btn');
        $home->assertSee('carousel-next-btn');

        // Galeria carrossel completa na página de detalhe (novo insumo e produto original)
        foreach (['papel-a4-500-folhas', 'playstation-5-slim-1tb'] as $slug) {
            $product = Product::where('slug', $slug)->firstOrFail();
            $show = $this->get(route('catalog.product', $product->slug));
            $show->assertOk();
            $show->assertSee($product->name);
            $show->assertSee('Galeria de fotos do produto');
            $show->assertSee('Vista principal');
            $show->assertSee('Visualização ampliada de imagens');
        }
    }

    public function test_product_and_category_factories_work(): void
    {
        $category = Category::factory()->create([
            'name' => 'Materiais Especiais',
            'slug' => 'materiais-especiais',
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Insumo de Teste Exclusivo',
            'slug' => 'insumo-teste-exclusivo',
            'price' => 149.90,
            'stock' => 15,
        ]);

        $this->assertDatabaseHas('categories', ['slug' => 'materiais-especiais']);
        $this->assertDatabaseHas('products', ['slug' => 'insumo-teste-exclusivo']);
        $this->assertEquals($category->id, $product->category->id);
        $this->assertCount(3, $product->gallerySlides());
    }

    public function test_authenticated_user_can_review_product_only_after_delivered_purchase(): void
    {
        $this->seed(CatalogSeeder::class);

        $company = Company::factory()->create();
        $user = User::factory()->forCompany($company)->create();
        $product = Product::where('slug', 'papel-a4-500-folhas')->firstOrFail();

        $this->actingAs($user)->post(route('catalog.review', $product->slug), [
            'rating' => 5,
            'comment' => 'Tentativa sem compra.',
        ])->assertSessionHasErrors('rating');

        $order = Order::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'subtotal_amount' => $product->price,
            'discount_amount' => 0.00,
            'shipping_cost' => 0.00,
            'total_amount' => $product->price,
            'credits_debited' => $product->price,
            'total_cost_snapshot' => $product->total_unit_cost,
            'estimated_das_amount' => $product->estimated_das_amount,
            'estimated_margin_amount' => $product->estimated_result_after_das,
            'status' => Order::STATUS_DELIVERED,
            'shipping_address' => 'Av. Paulista, 1000',
            'delivered_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_presentation' => $product->presentation,
            'quantity' => 1,
            'unit_price' => $product->price,
            'unit_cost_snapshot' => $product->total_unit_cost,
            'das_rate_snapshot' => $product->das_rate,
            'discount_allocated' => 0.00,
            'item_status' => Order::STATUS_DELIVERED,
        ]);

        $response = $this->actingAs($user)->post(route('catalog.review', $product->slug), [
            'rating' => 5,
            'comment' => 'Papel excelente para impressoras corporativas!',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => 5,
            'comment' => 'Papel excelente para impressoras corporativas!',
            'is_verified_purchase' => true,
        ]);
    }

    public function test_admin_can_create_and_update_products_and_categories(): void
    {
        $this->seed(CatalogSeeder::class);

        $admin = User::factory()->admin()->create();

        $catResponse = $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'EPI e Segurança',
            'icon' => 'shield-check',
        ]);
        $catResponse->assertRedirect();
        $category = Category::where('slug', 'epi-e-seguranca')->firstOrFail();

        $prodResponse = $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Luva Nitrílica Caixa 100un',
            'category_id' => $category->id,
            'unit' => 'caixa',
            'presentation' => 'Caixa com 100',
            'description' => 'Luva nitrílica resistente para limpeza e manipulação.',
            'cost_price' => 28.00,
            'inbound_freight' => 1.50,
            'other_costs' => 0.00,
            'markup_percent' => 35.0,
            'das_rate' => 4.0,
            'price' => 42.00,
            'stock' => 25,
            'min_stock' => 5,
        ]);
        $prodResponse->assertRedirect();
        $product = Product::where('slug', 'luva-nitrilica-caixa-100un')->firstOrFail();

        $updateResponse = $this->actingAs($admin)->patch(route('admin.products.update', $product), [
            'name' => 'Luva Nitrílica Reforçada Caixa 100un',
            'category_id' => $category->id,
            'cost_price' => 29.00,
            'inbound_freight' => 1.50,
            'price' => 44.50,
            'stock' => 30,
            'min_stock' => 6,
        ]);
        $updateResponse->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Luva Nitrílica Reforçada Caixa 100un',
            'price' => 44.50,
            'stock' => 30,
        ]);
    }

    public function test_multiterm_search_suggestions_price_bounds_and_category_override(): void
    {
        $this->seed(CatalogSeeder::class);

        // Multi-termo fora de ordem e busca por marca + categoria
        $this->get(route('catalog.index', ['q' => 'papel 500']))
            ->assertOk()
            ->assertSee('Papel A4');

        $this->get(route('catalog.index', ['q' => 'chamex a4']))
            ->assertOk()
            ->assertSee('Papel A4')
            ->assertDontSee('PlayStation 5 Slim');

        $this->get(route('catalog.index', ['q' => 'limpeza']))
            ->assertOk()
            ->assertSee('Detergente 500 ml');

        $suggestions = $this->getJson(route('catalog.suggestions', ['q' => 'papel 500']));
        $suggestions->assertOk()
            ->assertJsonFragment(['slug' => 'papel-a4-500-folhas']);

        // Inversão automática de min_price > max_price
        $swappedBounds = $this->get(route('catalog.index', [
            'min_price' => 360,
            'max_price' => 300,
        ]));
        $swappedBounds->assertOk()
            ->assertSee('Papel A4 Caixa Fechada (5.000 fls)')
            ->assertDontSee('Copo descartável 180 ml');

        // Troca explícita de categoria via ?category=... a partir de /categoria/...
        $overrideCategory = $this->get(route('catalog.category', ['categorySlug' => 'escritorio', 'category' => 'consoles']));
        $overrideCategory->assertOk()
            ->assertSee('PlayStation 5 Slim')
            ->assertDontSee('Papel A4');
    }

    public function test_related_products_stay_within_relevant_department(): void
    {
        $this->seed(CatalogSeeder::class);

        $ps5 = Product::where('slug', 'playstation-5-slim-1tb')->firstOrFail();
        $response = $this->get(route('catalog.product', $ps5->slug));
        $response->assertOk();
        $response->assertSee('Xbox Series X');
        $response->assertDontSee('Copo descartável 180 ml');
    }

    public function test_move_wishlist_to_cart_and_save_address_work_without_errors(): void
    {
        $this->seed(CatalogSeeder::class);

        $company = Company::factory()->create();
        $user = User::factory()->forCompany($company)->create();
        $product = Product::where('slug', 'papel-a4-500-folhas')->firstOrFail();

        $this->actingAs($user)
            ->post(route('wishlist.toggle', $product))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('wishlist.move-to-cart'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('address.save'), [
                'cep' => '01310-200',
                'number' => '1000',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'cep' => '01310-200',
            'is_default' => true,
        ]);
    }

    public function test_home_catalog_interleaves_categories_and_category_filter_isolates_selected_category(): void
    {
        $this->seed(CatalogSeeder::class);

        $page1Response = $this->get(route('catalog.index'));
        $page1Response->assertOk();

        $productsPage1 = $page1Response->viewData('products');
        $this->assertCount(12, $productsPage1->items());

        $parentIdsPage1 = collect($productsPage1->items())
            ->map(fn (Product $product): int => (int) ($product->category->parent_id ?: $product->category_id))
            ->values()
            ->all();

        $this->assertCount(6, array_unique(array_slice($parentIdsPage1, 0, 6)));
        $this->assertCount(6, array_unique(array_slice($parentIdsPage1, 6, 6)));

        for ($i = 1; $i < count($parentIdsPage1); $i++) {
            $this->assertNotSame(
                $parentIdsPage1[$i - 1],
                $parentIdsPage1[$i],
                "Itens consecutivos na posição {$i} repetiram a mesma categoria raiz."
            );
        }

        $page2Response = $this->get(route('catalog.index', ['page' => 2]));
        $page2Response->assertOk();
        $page2ParentIds = collect($page2Response->viewData('products')->items())
            ->map(fn (Product $product): int => (int) ($product->category->parent_id ?: $product->category_id))
            ->unique()
            ->all();
        $this->assertGreaterThanOrEqual(4, count($page2ParentIds));

        $escritorioCat = Category::with('children')->where('slug', 'escritorio')->firstOrFail();
        $escritorioAllowedIds = $escritorioCat->children->pluck('id')->push($escritorioCat->id)->map(fn ($id): int => (int) $id)->all();
        $escritorioPage = $this->get(route('catalog.category', 'escritorio'));
        $escritorioPage->assertOk();
        foreach ($escritorioPage->viewData('products')->items() as $item) {
            $this->assertContains((int) $item->category_id, $escritorioAllowedIds);
        }

        $limpezaCat = Category::with('children')->where('slug', 'limpeza')->firstOrFail();
        $limpezaAllowedIds = $limpezaCat->children->pluck('id')->push($limpezaCat->id)->map(fn ($id): int => (int) $id)->all();
        $limpezaQueryPage = $this->get(route('catalog.index', ['category' => 'limpeza']));
        $limpezaQueryPage->assertOk();
        foreach ($limpezaQueryPage->viewData('products')->items() as $item) {
            $this->assertContains((int) $item->category_id, $limpezaAllowedIds);
        }
    }

    public function test_home_storefront_renders_post_kit_dynamic_product_showcases_distinct_from_first_page(): void
    {
        $this->seed(CatalogSeeder::class);

        $response = $this->get(route('catalog.index'));
        $response->assertOk();

        $pageIds = collect($response->viewData('products')->items())->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $discoveryProducts = $response->viewData('postKitDiscoveryProducts');
        $departmentTabs = $response->viewData('departmentShowcaseTabs');
        $topDeals = $response->viewData('topDealsProducts');

        $this->assertCount(12, $discoveryProducts);

        $discoveryIds = $discoveryProducts->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->assertSame([], array_values(array_intersect($pageIds, $discoveryIds)));

        $discoveryRootIds = $discoveryProducts
            ->map(fn (Product $product): int => (int) ($product->category->parent_id ?: $product->category_id))
            ->unique()
            ->all();
        $this->assertCount(6, $discoveryRootIds);

        $this->assertCount(7, $departmentTabs);
        $this->assertSame('all', $departmentTabs[0]['key']);
        $this->assertNotEmpty($topDeals);

        $response->assertSeeInOrder([
            'Kits de Abastecimento Corporativo',
            'Continue explorando o catálogo',
            'Explore novas combinações por departamento',
        ]);
    }

    public function test_home_hero_renders_photographic_carousel_without_cluttered_kpi_cards(): void
    {
        $this->seed(CatalogSeeder::class);

        $heroSlides = [
            'images/hero/slide-1-escritorio.jpg',
            'images/hero/slide-2-copa.jpg',
            'images/hero/slide-3-tecnologia.jpg',
            'images/hero/slide-4-suprimentos.jpg',
        ];

        $response = $this->get(route('catalog.index'));
        $response->assertOk();

        foreach ($heroSlides as $relativePath) {
            $this->assertFileExists(public_path($relativePath));
            $response->assertSee($relativePath, false);
        }

        $response->assertDontSee('Em estoque imediato');
        $response->assertDontSee('Alternadas na vitrine');
        $response->assertDontSee('NexoSupri · Modelo A');
        $response->assertDontSee('app-canvas-bg', false);
        $response->assertSee('bg-paper', false);
        $response->assertSee('4500');
        $response->assertSee('hero-progress-bar', false);
        $response->assertSee('hero-ken-burns-active', false);
        $response->assertSee('card-shimmer-sweep', false);
        $response->assertSee('rotateY(180deg)', false);
        $response->assertSee('rounded-tr-[28px] rounded-bl-[28px]', false);
        $response->assertSee('Cartão Corporativo · Modelo A');
    }
}
