<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PriceAlert;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\CartPricing;
use App\Services\CatalogSearchService;
use App\Services\ShippingCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request, CatalogSearchService $searchService, ?string $categorySlug = null): View
    {
        $categories = Category::with('children')->whereNull('parent_id')->orderBy('id')->get();
        $selectedCategory = null;

        $query = Product::with(['category', 'images'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->where('stock', '>', 0);

        $effectiveSlug = $request->filled('category')
            ? trim((string) $request->input('category'))
            : $categorySlug;

        if ($effectiveSlug) {
            $selectedCategory = Category::where('slug', $effectiveSlug)->firstOrFail();
            $categoryIds = $selectedCategory->children()->pluck('id')->push($selectedCategory->id);
            $query->whereIn('category_id', $categoryIds);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $searchService->applySearch($query, $search);
        }

        $priceBounds = $searchService->normalizePriceBounds(
            $request->input('min_price'),
            $request->input('max_price'),
        );
        $effectiveMinPrice = $priceBounds['min'];
        $effectiveMaxPrice = $priceBounds['max'];

        if ($effectiveMinPrice !== null) {
            $query->where('price', '>=', $effectiveMinPrice);
        }

        if ($effectiveMaxPrice !== null) {
            $query->where('price', '<=', $effectiveMaxPrice);
        }

        if ($request->filled('min_rating')) {
            $minRating = max(1, min(5, (int) $request->input('min_rating')));
            $query->whereIn('id', function ($sub) use ($minRating): void {
                $sub->select('product_id')
                    ->from('product_reviews')
                    ->groupBy('product_id')
                    ->havingRaw('AVG(rating) >= ?', [$minRating]);
            });
        }

        match ($request->input('sort')) {
            'menor_preco' => $query->orderBy('price')->orderBy('id'),
            'maior_preco' => $query->orderByDesc('price')->orderBy('id'),
            'maior_desconto' => $query->orderByRaw('CASE WHEN compare_at_price > price THEN ((compare_at_price - price) / compare_at_price) ELSE 0 END DESC')->orderBy('id'),
            'melhor_avaliados' => $query->orderByDesc('reviews_avg_rating')->orderByDesc('reviews_count')->orderBy('id'),
            default => match (true) {
                $search !== '' => $searchService->applyRelevanceOrder($query, $search),
                $selectedCategory === null => $searchService->applyInterleavedOrder($query),
                default => $query->orderBy('id'),
            },
        };

        $products = $query->paginate(12)->withQueryString();

        $isHomeStorefront = ! $selectedCategory && ! $request->hasAny(['q', 'min_price', 'max_price', 'sort', 'category', 'min_rating']);

        $categoryProductCounts = Cache::remember('catalog_category_counts', 300, function (): array {
            return Product::query()
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->selectRaw('category_id, COUNT(*) as total')
                ->groupBy('category_id')
                ->pluck('total', 'category_id')
                ->map(fn ($total): int => (int) $total)
                ->all();
        });

        $parentCategoryCounts = [];
        foreach ($categories as $department) {
            $totalProducts = (int) ($categoryProductCounts[$department->id] ?? 0);
            foreach ($department->children as $child) {
                $totalProducts += (int) ($categoryProductCounts[$child->id] ?? 0);
            }
            $parentCategoryCounts[(int) $department->id] = $totalProducts;
        }

        $activeCoupons = collect();
        $featuredBundles = collect();
        $postKitDiscoveryProducts = collect();
        $departmentShowcaseTabs = [];
        $topDealsProducts = collect();

        if ($isHomeStorefront) {
            $activeCoupons = Coupon::query()
                ->where('is_active', true)
                ->where(function ($builder): void {
                    $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                })
                ->where(function ($builder): void {
                    $builder->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->orderByDesc('value')
                ->limit(3)
                ->get();

            $featuredBundles = ProductBundle::query()
                ->with(['items.product.category', 'items.product.images'])
                ->where('is_active', true)
                ->orderBy('id')
                ->limit(3)
                ->get();

            $showcases = $searchService->buildHomeShowcases(
                $products->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                $categories,
            );
            $postKitDiscoveryProducts = $showcases['postKitDiscoveryProducts'];
            $departmentShowcaseTabs = $showcases['departmentShowcaseTabs'];
            $topDealsProducts = $showcases['topDealsProducts'];
        }

        $wishlistIds = $this->resolveWishlistIds($request);
        $checkoutToken = (string) Str::uuid();
        $request->session()->put('checkout_token', $checkoutToken);

        return view('catalog.index', compact(
            'categories',
            'products',
            'selectedCategory',
            'isHomeStorefront',
            'categoryProductCounts',
            'parentCategoryCounts',
            'effectiveMinPrice',
            'effectiveMaxPrice',
            'activeCoupons',
            'featuredBundles',
            'postKitDiscoveryProducts',
            'departmentShowcaseTabs',
            'topDealsProducts',
            'wishlistIds',
            'checkoutToken',
        ));
    }

    public function show(
        Request $request,
        Product $product,
        ShippingCalculator $shippingCalculator,
        CatalogSearchService $searchService,
    ): View {
        abort_unless($product->is_active, 404);
        $product->load(['category.parent', 'images']);
        $product->loadAvg('reviews', 'rating');
        $product->loadCount('reviews');

        $reviews = $product->reviews()->with('user')->latest()->paginate(10);

        $ratingCounts = $product->reviews()
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->all();

        $totalReviews = (int) $product->reviews_count;
        $ratingDistribution = [];
        for ($star = 5; $star >= 1; $star--) {
            $count = (int) ($ratingCounts[$star] ?? 0);
            $ratingDistribution[$star] = [
                'count' => $count,
                'percent' => $totalReviews > 0 ? (int) round(($count / $totalReviews) * 100) : 0,
            ];
        }

        $soldUnitsCount = (int) OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_RETURNED]))
            ->sum('quantity');

        $defaultAddress = $request->user()
            ? $request->user()->addresses()->where('is_default', true)->first()
            : null;

        $savedCep = $request->session()->get('shipping_cep') ?: $defaultAddress?->cep;
        $savedAddress = $request->session()->get('shipping_address') ?: ($defaultAddress ? $defaultAddress->formattedLine() : null);

        $shippingEstimate = $savedCep
            ? $shippingCalculator->calculate(
                $savedCep,
                CartPricing::toCents((float) $product->price),
            )
            : null;

        $relatedProducts = $searchService->relatedProducts($product, 4);

        $wishlistIds = $this->resolveWishlistIds($request);
        $canReview = false;
        $userPriceAlert = null;

        if ($request->user()) {
            $canReview = $this->userHasPurchasedAndReceived($request->user(), $product->id);
            $userPriceAlert = PriceAlert::query()
                ->where('user_id', $request->user()->id)
                ->where('product_id', $product->id)
                ->first();
        }

        $this->recordRecentlyViewed($request, $product->id);
        $recentlyViewedProducts = $this->resolveRecentlyViewed($request, $product->id, 4);
        $questions = $product->questions()
            ->with(['user', 'answerer'])
            ->where('is_public', true)
            ->latest()
            ->limit(15)
            ->get();

        $checkoutToken = (string) Str::uuid();
        $request->session()->put('checkout_token', $checkoutToken);

        return view('catalog.show', compact(
            'product',
            'reviews',
            'questions',
            'ratingDistribution',
            'soldUnitsCount',
            'savedCep',
            'savedAddress',
            'shippingEstimate',
            'relatedProducts',
            'recentlyViewedProducts',
            'wishlistIds',
            'canReview',
            'userPriceAlert',
            'checkoutToken',
        ));
    }

    public function suggestions(Request $request, CatalogSearchService $searchService): JsonResponse
    {
        $search = trim((string) $request->input('q', ''));
        if (mb_strlen($search) < 2) {
            return response()->json([
                'products' => [],
                'categories' => [],
            ]);
        }

        $productsQuery = Product::query()
            ->with(['category', 'images'])
            ->withAvg('reviews', 'rating')
            ->where('is_active', true)
            ->where('stock', '>', 0);

        $searchService->applySearch($productsQuery, $search);
        $searchService->applyRelevanceOrder($productsQuery, $search);

        $products = $productsQuery
            ->limit(6)
            ->get()
            ->map(fn (Product $item): array => [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'slug' => $item->slug,
                'presentation' => $item->presentation ?: $item->unit,
                'price' => (float) $item->price,
                'formatted_price' => 'R$ '.number_format((float) $item->price, 2, ',', '.'),
                'credits' => number_format((float) $item->price, 2, ',', '.').' créditos',
                'thumbnail_url' => $item->thumbnail_url,
                'category_name' => $item->category?->name ?? 'Catálogo',
                'url' => route('catalog.product', $item->slug),
            ])
            ->values();

        $catLike = '%'.$searchService->escapeLike($search).'%';
        $categories = Category::query()
            ->where('name', 'like', $catLike)
            ->limit(3)
            ->get()
            ->map(fn (Category $cat): array => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'url' => route('catalog.category', $cat->slug),
            ])
            ->values();

        return response()->json([
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function storeReview(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $this->userHasPurchasedAndReceived($request->user(), $product->id)) {
            throw ValidationException::withMessages([
                'rating' => 'Apenas empresas que receberam este item ("Produto entregue") podem enviar avaliação verificada.',
            ]);
        }

        ProductReview::updateOrCreate(
            [
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
            ],
            [
                'rating' => (int) $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'is_verified_purchase' => true,
            ]
        );

        return back()->with('success', 'Avaliação corporativa registrada com sucesso!');
    }

    public function wishlistIndex(Request $request): View
    {
        $wishlistIds = $this->resolveWishlistIds($request);
        $products = Product::with(['category', 'images'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->whereIn('id', $wishlistIds)
            ->where('is_active', true)
            ->get();

        return view('catalog.wishlist', compact('products', 'wishlistIds'));
    }

    public function toggleWishlist(Request $request, Product $product): RedirectResponse
    {
        $wishlist = $this->resolveWishlistIds($request);

        if (in_array($product->id, $wishlist, true)) {
            $wishlist = array_values(array_diff($wishlist, [$product->id]));
            $request->session()->put('wishlist', $wishlist);

            if ($request->user()) {
                Wishlist::where('user_id', $request->user()->id)->where('product_id', $product->id)->delete();
            }

            return back()->with('success', "{$product->name} removido dos itens homologados.");
        }

        $wishlist[] = $product->id;
        $uniqueWishlist = array_values(array_unique($wishlist));
        $request->session()->put('wishlist', $uniqueWishlist);

        if ($request->user()) {
            Wishlist::firstOrCreate([
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
            ]);
        }

        return back()->with('success', "{$product->name} salvo nos itens homologados da empresa!");
    }

    public function moveWishlistToCart(Request $request): RedirectResponse
    {
        $wishlistIds = $this->resolveWishlistIds($request);
        if ($wishlistIds === []) {
            return back()->with('error', 'Sua lista de itens homologados está vazia.');
        }

        $products = Product::query()
            ->whereIn('id', $wishlistIds)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->get();

        if ($products->isEmpty()) {
            return back()->with('error', 'Nenhum item homologado está disponível em estoque no momento.');
        }

        $cart = $request->session()->get('cart', []);
        $movedCount = 0;

        foreach ($products as $product) {
            $currentQty = (int) ($cart[$product->id]['quantity'] ?? 0);
            $newQty = min($product->stock, max(1, $currentQty + 1));

            $cart[$product->id] = [
                'product_id' => $product->id,
                'quantity' => $newQty,
                'price_snapshot' => (float) $product->price,
            ];

            if ($request->user()) {
                CartItem::updateOrCreate(
                    ['user_id' => $request->user()->id, 'product_id' => $product->id],
                    ['quantity' => $newQty, 'price_snapshot' => (float) $product->price]
                );
            }

            $movedCount++;
        }

        $request->session()->put('cart', $cart);

        return redirect()->route('cart.index')->with(
            'success',
            "{$movedCount} ".($movedCount === 1 ? 'item adicionado' : 'itens adicionados').' ao carrinho!'
        );
    }

    public function saveAddress(Request $request, ShippingCalculator $shippingCalculator): RedirectResponse
    {
        $validated = $request->validate([
            'cep' => ['required', 'string', 'max:15'],
            'shipping_address' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:30'],
            'complement' => ['nullable', 'string', 'max:120'],
        ]);

        $viaCep = $shippingCalculator->lookupCep($validated['cep']);
        $number = $validated['number'] ?? 'S/N';

        $fullAddress = ! empty($validated['shipping_address'])
            ? $validated['shipping_address']
            : sprintf('%s, %s - %s, %s/%s', $viaCep['street'], $number, $viaCep['neighborhood'], $viaCep['city'], $viaCep['state']);

        $request->session()->put('shipping_cep', $viaCep['cep']);
        $request->session()->put('shipping_address', $fullAddress);

        if ($request->user()) {
            $company = $request->user()->primaryCompany();
            $request->user()->addresses()->update(['is_default' => false]);
            Address::updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'cep' => substr($viaCep['cep'], 0, 9),
                    'street' => $viaCep['street'],
                    'number' => $number,
                ],
                [
                    'company_id' => $company?->id,
                    'type' => 'delivery',
                    'label' => 'Unidade Principal',
                    'recipient_name' => $company?->trade_name ?: $request->user()->name,
                    'complement' => $validated['complement'] ?? null,
                    'neighborhood' => $viaCep['neighborhood'],
                    'city' => $viaCep['city'],
                    'state' => substr($viaCep['state'], 0, 2),
                    'is_default' => true,
                ]
            );
        }

        return back()->with('success', 'Endereço e CEP de entrega atualizados!');
    }

    public function quoteShipping(Request $request, ShippingCalculator $shippingCalculator): JsonResponse
    {
        $validated = $request->validate([
            'cep' => ['required', 'string', 'max:15'],
            'total_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $address = $shippingCalculator->lookupCep($validated['cep']);
        $quote = $shippingCalculator->calculate(
            $validated['cep'],
            (int) ($validated['total_cents'] ?? 0),
        );

        return response()->json([
            'address' => $address,
            'quote' => $quote,
        ]);
    }

    public function sitemap(): Response
    {
        $categories = Category::orderBy('id')->get();
        $products = Product::where('is_active', true)->orderBy('id')->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        $xml .= '  <url><loc>'.e(route('catalog.index')).'</loc><changefreq>daily</changefreq><priority>1.0</priority></url>'."\n";
        $xml .= '  <url><loc>'.e(route('plans.index')).'</loc><changefreq>weekly</changefreq><priority>0.9</priority></url>'."\n";

        foreach ($categories as $category) {
            $xml .= '  <url><loc>'.e(route('catalog.category', $category->slug)).'</loc><changefreq>weekly</changefreq><priority>0.8</priority></url>'."\n";
        }

        foreach ($products as $product) {
            $xml .= '  <url><loc>'.e(route('catalog.product', $product->slug)).'</loc><changefreq>weekly</changefreq><priority>0.9</priority></url>'."\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * @return list<int>
     */
    private function resolveWishlistIds(Request $request): array
    {
        if ($request->user()) {
            $dbIds = Wishlist::where('user_id', $request->user()->id)->pluck('product_id')->map(fn ($id) => (int) $id)->all();
            $sessionIds = array_map('intval', $request->session()->get('wishlist', []));
            $merged = array_values(array_unique(array_merge($dbIds, $sessionIds)));
            $request->session()->put('wishlist', $merged);

            return $merged;
        }

        return array_map('intval', $request->session()->get('wishlist', []));
    }

    private function userHasPurchasedAndReceived(User $user, int $productId): bool
    {
        $company = $user->primaryCompany();

        return Order::query()
            ->where(function ($q) use ($user, $company): void {
                $q->where('user_id', $user->id);
                if ($company) {
                    $q->orWhere('company_id', $company->id);
                }
            })
            ->where('status', Order::STATUS_DELIVERED)
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId))
            ->exists();
    }

    private function recordRecentlyViewed(Request $request, int $productId): void
    {
        $current = collect($request->session()->get('recently_viewed', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0 && $id !== $productId)
            ->values();

        $updated = $current->prepend($productId)->take(8)->values()->all();
        $request->session()->put('recently_viewed', $updated);
    }

    /**
     * @return Collection<int, Product>
     */
    private function resolveRecentlyViewed(Request $request, ?int $excludeProductId = null, int $limit = 4): Collection
    {
        $ids = collect($request->session()->get('recently_viewed', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0 && ($excludeProductId === null || $id !== $excludeProductId))
            ->unique()
            ->take($limit)
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $productsById = Product::with(['category', 'images'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return $ids->map(fn (int $id): ?Product => $productsById->get($id))->filter()->values();
    }
}
