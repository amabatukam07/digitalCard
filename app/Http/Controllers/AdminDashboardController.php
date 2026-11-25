<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Company;
use App\Models\Coupon;
use App\Models\CreditWallet;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Models\ProductQuestion;
use App\Models\User;
use App\Services\PriceAlertService;
use App\Services\PricingCalculator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDashboardController extends Controller
{
    public function index(Request $request, PricingCalculator $calculator): View
    {
        abort_unless((bool) $request->user()?->hasAdminAccess(), 403);

        $activeCompaniesCount = Company::where('status', Company::STATUS_ACTIVE)->count();
        $openCreditBalance = (float) CreditWallet::sum('balance');
        $creditsSold = (float) CreditWallet::sum('total_acquired');
        $creditsUsed = (float) CreditWallet::sum('total_spent');

        $pendingPaymentsCount = Payment::where('status', Payment::STATUS_PENDING)->count();
        $pendingPaymentsAmount = (float) Payment::where('status', Payment::STATUS_PENDING)->sum('amount');

        $validOrdersBase = Order::whereNotIn('status', [
            Order::STATUS_CANCELLED,
            Order::STATUS_RETURNED,
            Order::STATUS_AWAITING_APPROVAL,
        ]);

        $totalSales = (float) (clone $validOrdersBase)->sum('total_amount');
        $validOrdersCount = (clone $validOrdersBase)->count();
        $ticketAverage = $validOrdersCount > 0 ? round($totalSales / $validOrdersCount, 2) : 0.0;
        $estimatedDasTotal = (float) (clone $validOrdersBase)->sum('estimated_das_amount');
        $estimatedMarginTotal = (float) (clone $validOrdersBase)->sum('estimated_margin_amount');

        $pendingOrdersCount = Order::whereIn('status', [
            Order::STATUS_REQUESTED,
            Order::STATUS_SEPARATING,
            Order::STATUS_SHIPPED,
        ])->count();
        $deliveredOrdersCount = Order::where('status', Order::STATUS_DELIVERED)->count();

        $pendingPayments = Payment::with(['company', 'contract.plan', 'requestedBy'])
            ->where('status', Payment::STATUS_PENDING)
            ->latest()
            ->limit(8)
            ->get();

        $pendingOrders = Order::with(['company', 'user', 'items.product'])
            ->whereIn('status', [Order::STATUS_REQUESTED, Order::STATUS_SEPARATING, Order::STATUS_SHIPPED])
            ->latest()
            ->limit(8)
            ->get();

        $productQuery = Product::with('category')->latest();
        if ($productSearch = trim((string) $request->input('product_q'))) {
            $like = '%'.$productSearch.'%';
            $productQuery->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like);
            });
        }
        if ($request->boolean('low_stock')) {
            $productQuery->whereColumn('stock', '<=', 'min_stock');
        }

        $products = $productQuery->paginate(15)->withQueryString();
        $categories = Category::withCount(['products', 'children'])->orderBy('name')->get();
        $coupons = Coupon::orderByDesc('created_at')->get();
        $users = User::with('companies')->latest()->limit(25)->get();
        $auditLogs = AuditLog::with(['user', 'company'])->latest('created_at')->limit(20)->get();

        $lowStockProducts = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('stock')
            ->orderBy('name')
            ->limit(10)
            ->get();

        $pendingQuestions = ProductQuestion::query()
            ->with(['product', 'user'])
            ->whereNull('answer')
            ->latest()
            ->limit(10)
            ->get();

        $topProducts = OrderItem::query()
            ->selectRaw('product_id, MAX(product_name) as product_name, SUM(quantity) as total_units, SUM(quantity * unit_price) as total_revenue, SUM(quantity * unit_cost_snapshot) as total_cost')
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_RETURNED, Order::STATUS_AWAITING_APPROVAL]))
            ->groupBy('product_id')
            ->orderByDesc('total_units')
            ->limit(6)
            ->get();

        $deptRevenueRaw = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('categories as parent_cats', 'parent_cats.id', '=', 'categories.parent_id')
            ->whereNotIn('orders.status', [Order::STATUS_CANCELLED, Order::STATUS_RETURNED, Order::STATUS_AWAITING_APPROVAL])
            ->selectRaw('COALESCE(parent_cats.name, categories.name) as department_name, SUM(order_items.quantity * order_items.unit_price) as revenue, SUM(order_items.quantity) as units')
            ->groupByRaw('COALESCE(parent_cats.name, categories.name)')
            ->orderByDesc('revenue')
            ->limit(6)
            ->get();

        $maxDeptRevenue = max(1.0, (float) ($deptRevenueRaw->max('revenue') ?? 1.0));
        $salesByDepartment = $deptRevenueRaw->map(fn ($row): array => [
            'name' => (string) $row->department_name,
            'revenue' => (float) $row->revenue,
            'units' => (int) $row->units,
            'percent' => (int) min(100, max(6, round(((float) $row->revenue / $maxDeptRevenue) * 100))),
        ]);

        // Série mensal (últimos 6 meses) em PHP para compatibilidade MySQL e SQLite
        $sixMonthsStart = now()->subMonths(5)->startOfMonth();
        $monthlySeries = [];
        for ($i = 5; $i >= 0; $i--) {
            $mKey = now()->subMonths($i)->format('Y-m');
            $monthlySeries[$mKey] = [
                'label' => ucfirst(Carbon::createFromFormat('Y-m', $mKey)->locale('pt_BR')->translatedFormat('M/y')),
                'sales' => 0.0,
                'credits' => 0.0,
            ];
        }

        $recentOrdersForSeries = (clone $validOrdersBase)
            ->where('created_at', '>=', $sixMonthsStart)
            ->get(['total_amount', 'created_at']);

        foreach ($recentOrdersForSeries as $orderRow) {
            $k = $orderRow->created_at?->format('Y-m');
            if ($k && isset($monthlySeries[$k])) {
                $monthlySeries[$k]['sales'] = round($monthlySeries[$k]['sales'] + (float) $orderRow->total_amount, 2);
            }
        }

        $recentPaymentsForSeries = Payment::where('status', Payment::STATUS_APPROVED)
            ->where('created_at', '>=', $sixMonthsStart)
            ->get(['amount', 'approved_amount', 'created_at']);

        foreach ($recentPaymentsForSeries as $payRow) {
            $k = $payRow->created_at?->format('Y-m');
            if ($k && isset($monthlySeries[$k])) {
                $monthlySeries[$k]['credits'] = round($monthlySeries[$k]['credits'] + (float) ($payRow->approved_amount ?? $payRow->amount), 2);
            }
        }

        $maxSeriesValue = max(1.0, (float) collect($monthlySeries)->flatMap(fn ($r) => [$r['sales'], $r['credits']])->max());
        $monthlyChart = collect($monthlySeries)->map(fn (array $row): array => [
            'label' => $row['label'],
            'sales' => $row['sales'],
            'credits' => $row['credits'],
            'sales_pct' => $row['sales'] > 0 ? (int) min(100, max(6, round(($row['sales'] / $maxSeriesValue) * 100))) : 0,
            'credits_pct' => $row['credits'] > 0 ? (int) min(100, max(6, round(($row['credits'] / $maxSeriesValue) * 100))) : 0,
        ])->values();

        $ordersByStatusRaw = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $ordersByStatus = collect(OrderStatus::cases())->map(fn (OrderStatus $case): array => [
            'label' => $case->label(),
            'count' => (int) ($ordersByStatusRaw[$case->value] ?? 0),
        ]);

        $simCredits = max(500.0, (float) $request->input('sim_credits', 1000.0));
        $simMarkup = max(1.0, (float) $request->input('sim_markup', 35.0));
        $simDas = max(0.0, min(30.0, (float) $request->input('sim_das', 4.0)));
        $portfolioSimulation = $calculator->simulatePortfolio($simCredits, $simMarkup, $simDas);

        $creditUtilizationRate = $creditsSold > 0 ? round(($creditsUsed / $creditsSold) * 100, 1) : 0.0;
        $lowStockCompaniesCount = Company::where('status', Company::STATUS_ACTIVE)
            ->whereHas('wallet', fn ($q) => $q->whereColumn('balance', '<=', 'companies.low_balance_threshold'))
            ->count();

        $requestedTab = (string) $request->input('tab', '');
        $activeTab = match (true) {
            in_array($requestedTab, ['visao', 'fila', 'catalogo', 'governanca'], true) => $requestedTab,
            $request->filled('product_q') || $request->boolean('low_stock') || $request->filled('page') => 'catalogo',
            $request->hasAny(['sim_credits', 'sim_markup', 'sim_das']) => 'governanca',
            default => 'visao',
        };

        return view('admin.dashboard', compact(
            'activeCompaniesCount',
            'openCreditBalance',
            'creditsSold',
            'creditsUsed',
            'creditUtilizationRate',
            'lowStockCompaniesCount',
            'pendingPaymentsCount',
            'pendingPaymentsAmount',
            'totalSales',
            'ticketAverage',
            'estimatedDasTotal',
            'estimatedMarginTotal',
            'pendingOrdersCount',
            'deliveredOrdersCount',
            'pendingPayments',
            'pendingOrders',
            'products',
            'categories',
            'coupons',
            'users',
            'auditLogs',
            'lowStockProducts',
            'pendingQuestions',
            'topProducts',
            'salesByDepartment',
            'monthlyChart',
            'ordersByStatus',
            'portfolioSimulation',
            'simCredits',
            'simMarkup',
            'simDas',
            'activeTab',
        ));
    }

    public function storeProduct(Request $request, PricingCalculator $calculator): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCatalog(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:40', 'unique:products,sku'],
            'brand' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
            'presentation' => ['nullable', 'string', 'max:120'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['required', 'string', 'max:2000'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'inbound_freight' => ['nullable', 'numeric', 'min:0'],
            'other_costs' => ['nullable', 'numeric', 'min:0'],
            'markup_percent' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'das_rate' => ['nullable', 'numeric', 'min:0', 'max:40'],
            'price' => ['nullable', 'numeric', 'min:0.01'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0.01'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'image_path' => ['nullable', 'string', 'max:1000'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'gallery_files' => ['nullable', 'array', 'max:8'],
            'gallery_files.*' => ['image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $counter = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        $sku = trim((string) ($validated['sku'] ?? '')) ?: ('NX-'.strtoupper(Str::random(6)));

        $costPrice = round((float) $validated['cost_price'], 2);
        $inboundFreight = round((float) ($validated['inbound_freight'] ?? 0.0), 2);
        $otherCosts = round((float) ($validated['other_costs'] ?? 0.0), 2);
        $markupPercent = round((float) ($validated['markup_percent'] ?? config('nexosupri.default_markup_percent', 35.0)), 2);
        $dasRate = round((float) ($validated['das_rate'] ?? config('nexosupri.default_das_rate_percent', 4.0)), 2);

        $totalUnitCost = $calculator->totalUnitCost($costPrice, $inboundFreight, $otherCosts);
        $salePrice = ! empty($validated['price'])
            ? round((float) $validated['price'], 2)
            : $calculator->suggestedPrice($totalUnitCost, $markupPercent, $dasRate);

        $imagePath = $validated['image_path'] ?? null;
        if ($request->hasFile('image_file')) {
            $imagePath = $request->file('image_file')->store('products', 'public');
        }

        $compareAt = ! empty($validated['compare_at_price']) && (float) $validated['compare_at_price'] > $salePrice
            ? (float) $validated['compare_at_price']
            : null;

        $product = Product::create([
            'category_id' => (int) $validated['category_id'],
            'sku' => $sku,
            'brand' => $validated['brand'] ?? 'NexoSupri',
            'unit' => $validated['unit'],
            'presentation' => $validated['presentation'] ?? null,
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'],
            'cost_price' => $costPrice,
            'inbound_freight' => $inboundFreight,
            'other_costs' => $otherCosts,
            'markup_percent' => $markupPercent,
            'das_rate' => $dasRate,
            'price' => $salePrice,
            'compare_at_price' => $compareAt,
            'stock' => (int) $validated['stock'],
            'min_stock' => (int) $validated['min_stock'],
            'image_path' => $imagePath,
            'is_active' => true,
        ]);

        $this->attachGalleryUploads($request, $product, $imagePath);

        ProductPrice::create([
            'product_id' => $product->id,
            'cost_price' => $costPrice,
            'inbound_freight' => $inboundFreight,
            'other_costs' => $otherCosts,
            'markup_percent' => $markupPercent,
            'das_rate' => $dasRate,
            'sale_price' => $salePrice,
            'changed_by' => $request->user()->id,
            'reason' => 'Cadastro inicial do insumo',
            'effective_at' => now(),
        ]);

        Cache::forget('catalog_category_counts');
        Cache::forget('catalog_interleaved_ids');

        AuditLog::record(
            'product.created',
            $product,
            null,
            ['sku' => $product->sku, 'name' => $product->name, 'price' => (float) $product->price, 'stock' => $product->stock],
            $request->user()->id
        );

        return back()->with('success', "Insumo \"{$validated['name']}\" ({$sku}) cadastrado com sucesso.");
    }

    public function updateProduct(Request $request, Product $product, PricingCalculator $calculator): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCatalog(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:40', Rule::unique('products', 'sku')->ignore($product->id)],
            'brand' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:30'],
            'presentation' => ['nullable', 'string', 'max:120'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'inbound_freight' => ['nullable', 'numeric', 'min:0'],
            'other_costs' => ['nullable', 'numeric', 'min:0'],
            'markup_percent' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'das_rate' => ['nullable', 'numeric', 'min:0', 'max:40'],
            'price' => ['nullable', 'numeric', 'min:0.01'],
            'recalculate_price' => ['nullable', 'boolean'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0.01'],
            'clear_compare_at_price' => ['nullable', 'boolean'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'price_reason' => ['nullable', 'string', 'max:255'],
            'image_path' => ['nullable', 'string', 'max:1000'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'gallery_files' => ['nullable', 'array', 'max:8'],
            'gallery_files.*' => ['image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        $oldValues = [
            'name' => $product->name,
            'cost_price' => (float) $product->cost_price,
            'price' => (float) $product->price,
            'stock' => (int) $product->stock,
        ];

        $imagePath = ($validated['image_path'] ?? null) ?: $product->image_path;
        if ($request->hasFile('image_file')) {
            $imagePath = $request->file('image_file')->store('products', 'public');
        }

        $costPrice = round((float) $validated['cost_price'], 2);
        $inboundFreight = round((float) ($validated['inbound_freight'] ?? $product->inbound_freight), 2);
        $otherCosts = round((float) ($validated['other_costs'] ?? $product->other_costs), 2);
        $markupPercent = round((float) ($validated['markup_percent'] ?? $product->markup_percent), 2);
        $dasRate = round((float) ($validated['das_rate'] ?? $product->das_rate), 2);

        $totalUnitCost = $calculator->totalUnitCost($costPrice, $inboundFreight, $otherCosts);
        $salePrice = $request->boolean('recalculate_price') || empty($validated['price'])
            ? $calculator->suggestedPrice($totalUnitCost, $markupPercent, $dasRate)
            : round((float) $validated['price'], 2);

        if ($request->boolean('clear_compare_at_price') || ($request->has('compare_at_price') && empty($validated['compare_at_price']))) {
            $compareAt = null;
        } elseif (! empty($validated['compare_at_price']) && (float) $validated['compare_at_price'] > $salePrice) {
            $compareAt = (float) $validated['compare_at_price'];
        } elseif (! empty($validated['compare_at_price'])) {
            $compareAt = null;
        } else {
            $compareAt = $product->compare_at_price && (float) $product->compare_at_price > $salePrice
                ? $product->compare_at_price
                : null;
        }

        $product->update([
            'name' => $validated['name'],
            'sku' => ($validated['sku'] ?? null) ?: $product->sku,
            'brand' => ($validated['brand'] ?? null) ?: $product->brand,
            'unit' => ($validated['unit'] ?? null) ?: $product->unit,
            'presentation' => $validated['presentation'] ?? $product->presentation,
            'category_id' => (int) $validated['category_id'],
            'description' => array_key_exists('description', $validated) && $validated['description'] !== null && $validated['description'] !== ''
                ? $validated['description']
                : $product->description,
            'cost_price' => $costPrice,
            'inbound_freight' => $inboundFreight,
            'other_costs' => $otherCosts,
            'markup_percent' => $markupPercent,
            'das_rate' => $dasRate,
            'price' => $salePrice,
            'compare_at_price' => $compareAt,
            'stock' => (int) $validated['stock'],
            'min_stock' => (int) ($validated['min_stock'] ?? $product->min_stock),
            'image_path' => $imagePath,
        ]);

        $this->attachGalleryUploads($request, $product, $imagePath);

        if ((float) $oldValues['price'] !== (float) $salePrice || (float) $oldValues['cost_price'] !== (float) $costPrice) {
            ProductPrice::create([
                'product_id' => $product->id,
                'cost_price' => $costPrice,
                'inbound_freight' => $inboundFreight,
                'other_costs' => $otherCosts,
                'markup_percent' => $markupPercent,
                'das_rate' => $dasRate,
                'sale_price' => $salePrice,
                'changed_by' => $request->user()->id,
                'reason' => $validated['price_reason'] ?? 'Atualização administrativa de preço/custo',
                'effective_at' => now(),
            ]);
        }

        Cache::forget('catalog_category_counts');
        Cache::forget('catalog_interleaved_ids');

        AuditLog::record(
            'product.updated',
            $product,
            $oldValues,
            [
                'name' => $product->name,
                'cost_price' => (float) $product->cost_price,
                'price' => (float) $product->price,
                'stock' => (int) $product->stock,
            ],
            $request->user()->id
        );

        app(PriceAlertService::class)->dispatchTriggeredAlerts($product->fresh());

        return back()->with('success', "Insumo \"{$product->name}\" atualizado com sucesso.");
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCatalog(), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'icon' => ['nullable', 'string', 'max:30'],
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $counter = 1;

        while (Category::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'parent_id' => ! empty($validated['parent_id']) ? (int) $validated['parent_id'] : null,
            'icon' => ($validated['icon'] ?? null) ?: 'package',
        ]);

        Cache::forget('nav_categories_tree');
        Cache::forget('catalog_category_counts');
        Cache::forget('catalog_interleaved_ids');

        AuditLog::record('category.created', $category, null, ['name' => $category->name], $request->user()->id);

        return back()->with('success', "Categoria \"{$validated['name']}\" criada com sucesso.");
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCatalog(), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:30'],
        ]);

        $oldName = $category->name;
        $category->update([
            'name' => $validated['name'],
            'icon' => ($validated['icon'] ?? null) ?: $category->icon,
        ]);

        Cache::forget('nav_categories_tree');
        Cache::forget('catalog_category_counts');
        Cache::forget('catalog_interleaved_ids');

        AuditLog::record('category.updated', $category, ['name' => $oldName], ['name' => $category->name], $request->user()->id);

        return back()->with('success', "Categoria \"{$category->name}\" atualizada com sucesso.");
    }

    public function destroyCategory(Request $request, Category $category): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCatalog(), 403);
        if ($category->products()->exists() || $category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Não é possível excluir uma categoria com produtos ou subcategorias vinculadas.',
            ]);
        }

        $name = $category->name;
        AuditLog::record('category.deleted', $category, ['name' => $name], null, $request->user()->id);
        $category->delete();

        Cache::forget('nav_categories_tree');
        Cache::forget('catalog_category_counts');
        Cache::forget('catalog_interleaved_ids');

        return back()->with('success', "Categoria \"{$name}\" excluída.");
    }

    public function toggleProduct(Request $request, Product $product): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCatalog(), 403);
        $oldActive = (bool) $product->is_active;
        $product->update(['is_active' => ! $oldActive]);
        Cache::forget('catalog_category_counts');
        Cache::forget('catalog_interleaved_ids');

        AuditLog::record(
            'product.toggled',
            $product,
            ['is_active' => $oldActive],
            ['is_active' => (bool) $product->is_active],
            $request->user()->id
        );

        return back()->with('success', $product->is_active
            ? "Insumo \"{$product->name}\" reativado no catálogo."
            : "Insumo \"{$product->name}\" desativado do catálogo.");
    }

    public function storeCoupon(Request $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCatalog(), 403);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'label' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:0.01'],
            'min_total' => ['nullable', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $code = strtoupper(trim($validated['code']));

        $coupon = Coupon::updateOrCreate(
            ['code' => $code],
            [
                'label' => $validated['label'],
                'type' => $validated['type'],
                'value' => (float) $validated['value'],
                'min_total' => (float) ($validated['min_total'] ?? 0.0),
                'max_uses' => $validated['max_uses'] ?? null,
                'per_user_limit' => (int) ($validated['per_user_limit'] ?? 1),
                'starts_at' => now()->subMinute(),
                'ends_at' => ! empty($validated['ends_at']) ? $validated['ends_at'] : null,
                'is_active' => true,
            ]
        );

        AuditLog::record(
            'coupon.saved',
            $coupon,
            null,
            ['code' => $coupon->code, 'type' => $coupon->type, 'value' => (float) $coupon->value],
            $request->user()->id
        );

        return back()->with('success', "Condição comercial/cupom \"{$coupon->code}\" salvo com sucesso.");
    }

    public function toggleCoupon(Request $request, Coupon $coupon): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCatalog(), 403);
        $oldActive = (bool) $coupon->is_active;
        $coupon->update(['is_active' => ! $oldActive]);

        AuditLog::record(
            'coupon.toggled',
            $coupon,
            ['is_active' => $oldActive],
            ['is_active' => (bool) $coupon->is_active],
            $request->user()->id
        );

        return back()->with('success', $coupon->is_active
            ? "Cupom {$coupon->code} ativado."
            : "Cupom {$coupon->code} desativado.");
    }

    public function exportSalesCsv(Request $request): StreamedResponse
    {
        abort_unless((bool) $request->user()?->hasAdminAccess(), 403);

        $query = Order::with(['company', 'user'])
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->orderByDesc('created_at');

        $filename = 'relatorio-vendas-nexosupri-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Pedido',
                'Data',
                'Empresa',
                'CNPJ',
                'Solicitante',
                'Status',
                'Subtotal (R$)',
                'Desconto (R$)',
                'Frete (R$)',
                'Total (R$ / Créditos)',
                'Custo Estimado (R$)',
                'DAS Estimado (R$)',
                'Resultado Estimado (R$)',
            ], ';');

            foreach ($query->cursor() as $order) {
                fputcsv($handle, [
                    '#PED-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
                    $order->created_at?->format('d/m/Y H:i'),
                    $this->sanitizeCsvCell($order->company?->trade_name ?? 'N/A'),
                    $this->sanitizeCsvCell($order->company?->cnpj ?? ''),
                    $this->sanitizeCsvCell($order->user?->name ?? 'N/A'),
                    $this->sanitizeCsvCell($order->status),
                    number_format((float) $order->subtotal_amount, 2, ',', ''),
                    number_format((float) $order->discount_amount, 2, ',', ''),
                    number_format((float) $order->shipping_cost, 2, ',', ''),
                    number_format((float) $order->total_amount, 2, ',', ''),
                    number_format((float) $order->total_cost_snapshot, 2, ',', ''),
                    number_format((float) $order->estimated_das_amount, 2, ',', ''),
                    number_format((float) $order->estimated_margin_amount, 2, ',', ''),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportCompanyCreditsCsv(Request $request): StreamedResponse
    {
        abort_unless((bool) $request->user()?->hasAdminAccess(), 403);

        $query = Company::with('wallet')->orderBy('trade_name');
        $filename = 'saldos-creditos-empresas-nexosupri-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Empresa', 'Razão Social', 'CNPJ', 'Status', 'Créditos Adquiridos', 'Créditos Utilizados', 'Saldo em Aberto'], ';');

            foreach ($query->cursor() as $company) {
                $wallet = $company->wallet;
                fputcsv($handle, [
                    $this->sanitizeCsvCell($company->trade_name),
                    $this->sanitizeCsvCell($company->legal_name),
                    $this->sanitizeCsvCell($company->cnpj),
                    $this->sanitizeCsvCell($company->status),
                    number_format((float) ($wallet?->total_acquired ?? 0), 2, ',', ''),
                    number_format((float) ($wallet?->total_spent ?? 0), 2, ',', ''),
                    number_format((float) ($wallet?->balance ?? 0), 2, ',', ''),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function updateUserRole(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless((bool) $actor?->is_admin || $actor?->roleEnum() === UserRole::Admin, 403);
        abort_if((int) $actor->id === (int) $user->id, 403, 'Não é permitido alterar o próprio papel administrativo.');

        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $newRole = UserRole::from($validated['role']);
        $oldRole = $user->roleEnum()->value;

        $user->forceFill([
            'role' => $newRole->value,
            'is_admin' => $newRole === UserRole::Admin,
        ])->save();

        AuditLog::record(
            'user.role_updated',
            $user,
            ['role' => $oldRole],
            ['role' => $newRole->value],
            $actor->id
        );

        return back()->with('success', "Papel de {$user->name} atualizado para {$newRole->label()}.");
    }

    private function attachGalleryUploads(Request $request, Product $product, ?string $primaryImagePath): void
    {
        $uploadedPaths = [];
        if ($primaryImagePath && $product->images()->count() === 0) {
            $uploadedPaths[] = $primaryImagePath;
        }

        foreach ($request->file('gallery_files', []) as $file) {
            if ($file && $file->isValid()) {
                $uploadedPaths[] = $file->store('products', 'public');
            }
        }

        if ($uploadedPaths === []) {
            return;
        }

        if (! $product->image_path) {
            $product->update(['image_path' => $uploadedPaths[0]]);
        }

        $nextOrder = (int) $product->images()->max('sort_order') + 1;
        foreach ($uploadedPaths as $path) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $path,
                'alt' => $product->name,
                'caption' => 'Imagem '.$nextOrder,
                'sort_order' => $nextOrder,
            ]);
            $nextOrder++;
        }
    }

    private function sanitizeCsvCell(?string $value): string
    {
        $text = (string) ($value ?? '');
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$text;
        }

        return $text;
    }
}
