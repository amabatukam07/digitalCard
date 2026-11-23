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
use App\Models\Wishlist;
use App\Services\CartPricing;
use App\Services\LoyaltyTierService;
use App\Services\ShippingCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request, ?string $categorySlug = null): View
    {
        $categories = Category::with('children')->whereNull('parent_id')->get();
        $selectedCategory = null;

        $query = Product::with('category')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->where('stock', '>', 0);

        $effectiveSlug = $categorySlug ?: $request->input('category');

        if ($effectiveSlug) {
            $selectedCategory = Category::where('slug', $effectiveSlug)->firstOrFail();
            $categoryIds = $selectedCategory->children()->pluck('id')->push($selectedCategory->id);
            $query->whereIn('category_id', $categoryIds);
        }

        if ($search = trim((string) $request->input('q'))) {
            $escaped = addcslashes($search, '%_\\');
            $query->where(function ($builder) use ($escaped) {
                $builder->whereRaw("name LIKE ? ESCAPE '\\'", ["%{$escaped}%"])
                    ->orWhereRaw("description LIKE ? ESCAPE '\\'", ["%{$escaped}%"]);
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->input('max_price'));
        }

        if ($request->boolean('cashback')) {
            $query->where('cashback_percent', '>', 0);
        }

        if ($request->filled('min_rating')) {
            $minRating = max(1, min(5, (int) $request->input('min_rating')));
            $query->whereIn('id', function ($sub) use ($minRating) {
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
            'maior_cashback' => $query->orderByDesc('cashback_percent')->orderBy('id'),
            'melhor_avaliados' => $query->orderByDesc('reviews_avg_rating')->orderByDesc('reviews_count')->orderBy('id'),
            default => $query->orderBy('id'),
        };

        $products = $query->paginate(20)->withQueryString();

        $isHomeStorefront = ! $selectedCategory && ! $request->hasAny(['q', 'min_price', 'max_price', 'sort', 'cashback', 'category', 'min_rating']);

        $featuredDeals = collect();
        $topCashbackProducts = collect();
        $activeCoupons = collect();
        $departmentCards = collect();
        $recentlyViewedProducts = collect();
        $featuredBundles = collect();
        $promoEndsAt = null;
        if ($isHomeStorefront) {
            $featuredDeals = Product::with('category')
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->whereNotNull('compare_at_price')
                ->orderByRaw('((compare_at_price - price) / compare_at_price) DESC')
                ->limit(8)
                ->get();

            $topCashbackProducts = Product::with('category')
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->where('cashback_percent', '>', 0)
                ->orderByDesc('cashback_percent')
                ->orderBy('price')
                ->limit(4)
                ->get();

            $activeCoupons = Coupon::query()
                ->where('is_active', true)
                ->where(function ($builder): void {
                    $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                })
                ->where(function ($builder): void {
                    $builder->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->orderByDesc('value')
                ->limit(4)
                ->get();

            $productCountsByCategory = Product::query()
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->selectRaw('category_id, COUNT(*) as total')
                ->groupBy('category_id')
                ->pluck('total', 'category_id');

            $departmentCards = $categories->map(function (Category $department) use ($productCountsByCategory): array {
                $totalProducts = (int) ($productCountsByCategory[$department->id] ?? 0);
                foreach ($department->children as $child) {
                    $totalProducts += (int) ($productCountsByCategory[$child->id] ?? 0);
                }

                return [
                    'name' => $department->name,
                    'slug' => $department->slug,
                    'icon' => $department->icon ?: 'package',
                    'subcategories_count' => $department->children->count(),
                    'products_count' => $totalProducts,
                ];
            });

            $recentlyViewedProducts = $this->resolveRecentlyViewed($request, null, 4);

            $featuredBundles = ProductBundle::query()
                ->with(['items.product.category'])
                ->where('is_active', true)
                ->orderBy('id')
                ->limit(4)
                ->get();

            // H3(i): Usa o ends_at real de um cupom ativo no banco
            $promoEndsAt = Coupon::where('is_active', true)
                ->whereNotNull('ends_at')
                ->where('ends_at', '>', now())
                ->orderBy('ends_at')
                ->value('ends_at');
        }

        $wishlistIds = $this->resolveWishlistIds($request);
        $checkoutToken = (string) Str::uuid();
        $request->session()->put('checkout_token', $checkoutToken);

        return view('catalog.index', compact(
            'categories',
            'products',
            'selectedCategory',
            'isHomeStorefront',
            'featuredDeals',
            'topCashbackProducts',
            'activeCoupons',
            'departmentCards',
            'recentlyViewedProducts',
            'featuredBundles',
            'promoEndsAt',
            'wishlistIds',
            'checkoutToken',
        ));
    }

    public function show(Request $request, Product $product, ShippingCalculator $shippingCalculator): View
    {
        abort_unless($product->is_active, 404);
        $product->load(['category.parent']);
        $product->loadAvg('reviews', 'rating');
        $product->loadCount('reviews');

        $reviews = $product->reviews()->with('user')->latest()->paginate(10);

        // Distribuição real de estrelas calculada via GROUP BY no banco
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

        // H3(iii): Contagem real de unidades vendidas em pedidos válidos
        $soldUnitsCount = (int) OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_RETURNED]))
            ->sum('quantity');

        // H4 + H3(ii): Endereço padrão real do usuário logado (ou sessão) sem fallback fixo de Maceió/Rio Largo
        $defaultAddress = $request->user()
            ? $request->user()->addresses()->where('is_default', true)->first()
            : null;

        $savedCep = $request->session()->get('shipping_cep') ?: $defaultAddress?->cep;
        $savedAddress = $request->session()->get('shipping_address') ?: ($defaultAddress ? $defaultAddress->formattedLine() : null);

        $loyaltySummary = app(LoyaltyTierService::class)->summaryFor($request->user());

        $shippingEstimate = $savedCep
            ? $shippingCalculator->calculate(
                $savedCep,
                CartPricing::toCents((float) $product->price),
                $loyaltySummary['free_shipping_min_cents']
            )
            : null;

        $relatedProducts = Product::with('category')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->limit(4)
            ->get();

        if ($relatedProducts->count() < 4) {
            $extraProducts = Product::with('category')
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->where('id', '!=', $product->id)
                ->whereNotIn('id', $relatedProducts->pluck('id'))
                ->orderBy('id')
                ->limit(4 - $relatedProducts->count())
                ->get();

            $relatedProducts = $relatedProducts->concat($extraProducts);
        }

        $wishlistIds = $this->resolveWishlistIds($request);
        $canReview = false;
        $userPriceAlert = null;

        if ($request->user()) {
            $canReview = $this->userHasPurchasedAndReceived($request->user()->id, $product->id);
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
            'loyaltySummary',
            'checkoutToken',
        ));
    }

    public function suggestions(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('q', ''));
        if (mb_strlen($search) < 2) {
            return response()->json([
                'products' => [],
                'categories' => [],
            ]);
        }

        $escaped = addcslashes($search, '%_\\');

        $products = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->where(function ($builder) use ($escaped): void {
                $builder->whereRaw("name LIKE ? ESCAPE '\\'", ["%{$escaped}%"])
                    ->orWhereRaw("description LIKE ? ESCAPE '\\'", ["%{$escaped}%"]);
            })
            ->orderByDesc('cashback_percent')
            ->limit(6)
            ->get()
            ->map(fn (Product $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
                'price' => (float) $item->price,
                'formatted_price' => 'R$ '.number_format((float) $item->price, 2, ',', '.'),
                'cashback_percent' => (float) $item->cashback_percent,
                'thumbnail_url' => $item->thumbnail_url,
                'category_name' => $item->category?->name ?? 'Catálogo',
                'url' => route('catalog.product', $item->slug),
            ])
            ->values();

        $categories = Category::query()
            ->whereRaw("name LIKE ? ESCAPE '\\'", ["%{$escaped}%"])
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

        if (! $this->userHasPurchasedAndReceived($request->user()->id, $product->id)) {
            throw ValidationException::withMessages([
                'rating' => 'Apenas clientes que compraram e já receberam este produto ("Produto entregue") podem enviar uma avaliação verificada.',
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

        return back()->with('success', 'Avaliação de compra verificada registrada com sucesso!');
    }

    public function wishlistIndex(Request $request): View
    {
        $wishlistIds = $this->resolveWishlistIds($request);
        $products = Product::with('category')
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

            return back()->with('success', "{$product->name} removido da sua Lista de Desejos.");
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

        return back()->with('success', "{$product->name} salvo na sua Lista de Desejos!");
    }

    public function moveWishlistToCart(Request $request): RedirectResponse
    {
        $wishlistIds = $this->resolveWishlistIds($request);
        if ($wishlistIds === []) {
            return back()->with('error', 'Sua lista de desejos está vazia.');
        }

        $products = Product::query()
            ->whereIn('id', $wishlistIds)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->get();

        if ($products->isEmpty()) {
            return back()->with('error', 'Nenhum produto favoritado está disponível em estoque no momento.');
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
            "{$movedCount} ".($movedCount === 1 ? 'favorito adicionado' : 'favoritos adicionados').' ao seu carrinho!'
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
            $request->user()->addresses()->update(['is_default' => false]);
            Address::updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'cep' => substr($viaCep['cep'], 0, 9),
                    'street' => $viaCep['street'],
                    'number' => $number,
                ],
                [
                    'label' => 'Principal',
                    'recipient_name' => $request->user()->name,
                    'complement' => $validated['complement'] ?? null,
                    'neighborhood' => $viaCep['neighborhood'],
                    'city' => $viaCep['city'],
                    'state' => substr($viaCep['state'], 0, 2),
                    'is_default' => true,
                ]
            );
        }

        return back()->with('success', 'Endereço e CEP de entrega atualizados com cálculo de frete!');
    }

    public function quoteShipping(Request $request, ShippingCalculator $shippingCalculator): JsonResponse
    {
        $validated = $request->validate([
            'cep' => ['required', 'string', 'max:15'],
            'total_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $loyaltySummary = app(LoyaltyTierService::class)->summaryFor($request->user());
        $address = $shippingCalculator->lookupCep($validated['cep']);
        $quote = $shippingCalculator->calculate(
            $validated['cep'],
            (int) ($validated['total_cents'] ?? 0),
            $loyaltySummary['free_shipping_min_cents']
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

    private function userHasPurchasedAndReceived(int $userId, int $productId): bool
    {
        return Order::where('user_id', $userId)
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

        $productsById = Product::with('category')
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
