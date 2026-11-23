<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CardTransaction;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PriceAlert;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\Recharge;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Models\VirtualCard;
use App\Services\PriceAlertService;
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
    public function index(Request $request): View
    {
        $totalBalance = (float) VirtualCard::sum('balance');
        $totalCashback = (float) VirtualCard::sum('cashback_earned');
        $pendingRechargesCount = Recharge::where('status', Recharge::STATUS_PENDING)->count();
        $pendingRechargesAmount = (float) Recharge::where('status', Recharge::STATUS_PENDING)->sum('amount');
        $totalSales = (float) Order::whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_RETURNED])->sum('total_amount');
        $pendingOrdersCount = Order::whereIn('status', [Order::STATUS_REQUESTED, Order::STATUS_SEPARATING, Order::STATUS_SHIPPED])->count();
        $deliveredOrdersCount = Order::where('status', Order::STATUS_DELIVERED)->count();

        $pendingRecharges = Recharge::with('virtualCard.user')
            ->where('status', Recharge::STATUS_PENDING)
            ->latest()
            ->limit(10)
            ->get();

        $pendingOrders = Order::with('user', 'items.product')
            ->whereIn('status', [Order::STATUS_REQUESTED, Order::STATUS_SEPARATING, Order::STATUS_SHIPPED])
            ->latest()
            ->limit(10)
            ->get();

        // Busca e paginação de produtos no painel administrativo
        $productQuery = Product::with('category')->latest();
        if ($productSearch = trim((string) $request->input('product_q'))) {
            $escaped = addcslashes($productSearch, '%_\\');
            $productQuery->whereRaw("name LIKE ? ESCAPE '\\'", ["%{$escaped}%"]);
        }

        $products = $productQuery->paginate(15)->withQueryString();
        $categories = Category::withCount(['products', 'children'])->orderBy('name')->get();
        $coupons = Coupon::orderByDesc('created_at')->get();
        $users = User::with('virtualCard')->latest()->limit(20)->get();
        $auditLogs = AuditLog::with('user')->latest('created_at')->limit(20)->get();

        $lowStockProducts = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('stock', '<=', 5)
            ->orderBy('stock')
            ->orderBy('name')
            ->limit(8)
            ->get();

        $pendingQuestions = ProductQuestion::query()
            ->with(['product', 'user'])
            ->whereNull('answer')
            ->latest()
            ->limit(10)
            ->get();

        $fintechMetrics = [
            'goals_total' => (float) SavingsGoal::sum('current_amount'),
            'goals_count' => SavingsGoal::count(),
            'active_gift_cards_total' => (float) GiftCard::where('status', GiftCard::STATUS_ACTIVE)->sum('remaining_amount'),
            'active_gift_cards_count' => GiftCard::where('status', GiftCard::STATUS_ACTIVE)->count(),
            'p2p_volume_total' => (float) abs((float) CardTransaction::where('type', CardTransaction::TYPE_TRANSFER_OUT)->sum('amount')),
            'active_price_alerts_count' => PriceAlert::whereNull('notified_at')->count(),
        ];

        $topProducts = OrderItem::query()
            ->selectRaw('product_id, MAX(product_name) as product_name, SUM(quantity) as total_units, SUM(quantity * unit_price) as total_revenue')
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_RETURNED]))
            ->groupBy('product_id')
            ->orderByDesc('total_units')
            ->limit(5)
            ->get();

        $deptRevenueRaw = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('categories as parent_cats', 'parent_cats.id', '=', 'categories.parent_id')
            ->whereNotIn('orders.status', [Order::STATUS_CANCELLED, Order::STATUS_RETURNED])
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

        return view('admin.dashboard', compact(
            'totalBalance',
            'totalCashback',
            'pendingRechargesCount',
            'pendingRechargesAmount',
            'totalSales',
            'pendingOrdersCount',
            'deliveredOrdersCount',
            'pendingRecharges',
            'pendingOrders',
            'products',
            'categories',
            'coupons',
            'users',
            'auditLogs',
            'lowStockProducts',
            'pendingQuestions',
            'fintechMetrics',
            'topProducts',
            'salesByDepartment',
        ));
    }

    /**
     * Cadastra produto sem fabricar compare_at_price falso, limitando cashback_percent e suportando upload de imagem.
     */
    public function storeProduct(Request $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCatalog(), 403);
        $maxCashback = (float) config('digitalcard.max_cashback_percent', 15.0);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['required', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0.01'],
            'cashback_percent' => ['required', 'numeric', 'min:0', 'max:'.$maxCashback],
            'stock' => ['required', 'integer', 'min:0'],
            'image_path' => ['nullable', 'string', 'max:1000'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $counter = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        $imagePath = $validated['image_path'] ?? null;
        if ($request->hasFile('image_file')) {
            $imagePath = $request->file('image_file')->store('products', 'public');
        }

        // Somente grava compare_at_price quando realmente informado pelo administrador e maior que o preço
        $compareAt = ! empty($validated['compare_at_price']) && (float) $validated['compare_at_price'] > (float) $validated['price']
            ? (float) $validated['compare_at_price']
            : null;

        $product = Product::create([
            'category_id' => (int) $validated['category_id'],
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'],
            'price' => (float) $validated['price'],
            'compare_at_price' => $compareAt,
            'cashback_percent' => (float) $validated['cashback_percent'],
            'stock' => (int) $validated['stock'],
            'image_path' => $imagePath,
            'is_active' => true,
        ]);

        AuditLog::record(
            'product.created',
            $product,
            null,
            ['name' => $product->name, 'price' => (float) $product->price, 'stock' => $product->stock],
            $request->user()->id
        );

        return back()->with('success', "Produto \"{$validated['name']}\" cadastrado com sucesso no catálogo.");
    }

    /**
     * Permite editar descrição, limpar compare_at_price e enviar upload de imagem.
     */
    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCatalog(), 403);
        $maxCashback = (float) config('digitalcard.max_cashback_percent', 15.0);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0.01'],
            'clear_compare_at_price' => ['nullable', 'boolean'],
            'cashback_percent' => ['required', 'numeric', 'min:0', 'max:'.$maxCashback],
            'stock' => ['required', 'integer', 'min:0'],
            'image_path' => ['nullable', 'string', 'max:1000'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $oldValues = [
            'name' => $product->name,
            'price' => (float) $product->price,
            'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
            'stock' => (int) $product->stock,
        ];

        $imagePath = ($validated['image_path'] ?? null) ?: $product->image_path;
        if ($request->hasFile('image_file')) {
            $imagePath = $request->file('image_file')->store('products', 'public');
        }

        if ($request->boolean('clear_compare_at_price') || ($request->has('compare_at_price') && empty($validated['compare_at_price']))) {
            $compareAt = null;
        } elseif (! empty($validated['compare_at_price']) && (float) $validated['compare_at_price'] > (float) $validated['price']) {
            $compareAt = (float) $validated['compare_at_price'];
        } elseif (! empty($validated['compare_at_price'])) {
            $compareAt = null;
        } else {
            $compareAt = $product->compare_at_price && (float) $product->compare_at_price > (float) $validated['price']
                ? $product->compare_at_price
                : null;
        }

        $product->update([
            'name' => $validated['name'],
            'category_id' => (int) $validated['category_id'],
            'description' => array_key_exists('description', $validated) && $validated['description'] !== null && $validated['description'] !== ''
                ? $validated['description']
                : $product->description,
            'price' => (float) $validated['price'],
            'compare_at_price' => $compareAt,
            'cashback_percent' => (float) $validated['cashback_percent'],
            'stock' => (int) $validated['stock'],
            'image_path' => $imagePath,
        ]);

        AuditLog::record(
            'product.updated',
            $product,
            $oldValues,
            [
                'name' => $product->name,
                'price' => (float) $product->price,
                'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
                'stock' => (int) $product->stock,
            ],
            $request->user()->id
        );

        app(PriceAlertService::class)->dispatchTriggeredAlerts($product->fresh());

        return back()->with('success', "Produto \"{$product->name}\" atualizado com sucesso.");
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCatalog(), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'icon' => ['nullable', 'string', 'max:20'],
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

        AuditLog::record('category.created', $category, null, ['name' => $category->name], $request->user()->id);

        return back()->with('success', "Categoria \"{$validated['name']}\" criada com sucesso.");
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCatalog(), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:20'],
        ]);

        $oldName = $category->name;
        $category->update([
            'name' => $validated['name'],
            'icon' => ($validated['icon'] ?? null) ?: $category->icon,
        ]);

        Cache::forget('nav_categories_tree');

        AuditLog::record('category.updated', $category, ['name' => $oldName], ['name' => $category->name], $request->user()->id);

        return back()->with('success', "Categoria \"{$category->name}\" atualizada com sucesso.");
    }

    /**
     * Exclui categoria com segurança (bloqueia se possuir produtos ou subcategorias).
     */
    public function destroyCategory(Request $request, Category $category): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCatalog(), 403);
        if ($category->products()->exists() || $category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Não é possível excluir uma categoria que possui produtos ou subcategorias vinculadas.',
            ]);
        }

        $name = $category->name;
        AuditLog::record('category.deleted', $category, ['name' => $name], null, $request->user()->id);
        $category->delete();

        Cache::forget('nav_categories_tree');

        return back()->with('success', "Categoria \"{$name}\" excluída com sucesso.");
    }

    public function toggleProduct(Request $request, Product $product): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCatalog(), 403);
        $oldActive = (bool) $product->is_active;
        $product->update(['is_active' => ! $oldActive]);

        AuditLog::record(
            'product.toggled',
            $product,
            ['is_active' => $oldActive],
            ['is_active' => (bool) $product->is_active],
            $request->user()->id
        );

        return back()->with('success', $product->is_active
            ? "Produto \"{$product->name}\" reativado no catálogo."
            : "Produto \"{$product->name}\" desativado do catálogo.");
    }

    /**
     * CRUD de Cupons Promocionais no painel administrativo.
     */
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

        return back()->with('success', "Cupom \"{$coupon->code}\" salvo com sucesso.");
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

    /**
     * Exporta relatório de vendas por período em formato CSV com BOM UTF-8 e prevenção a Formula Injection.
     */
    public function exportSalesCsv(Request $request): StreamedResponse
    {
        $query = Order::with('user')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->orderByDesc('created_at');

        $filename = 'relatorio-vendas-digitalcard-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Pedido', 'Data', 'Cliente', 'Email', 'Status', 'Subtotal (R$)', 'Desconto (R$)', 'Frete (R$)', 'Total Pago (R$)', 'Cashback (R$)'], ';');

            foreach ($query->cursor() as $order) {
                fputcsv($handle, [
                    '#PED-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
                    $order->created_at?->format('d/m/Y H:i'),
                    $this->sanitizeCsvCell($order->user?->name ?? 'N/A'),
                    $this->sanitizeCsvCell($order->user?->email ?? 'N/A'),
                    $this->sanitizeCsvCell($order->status),
                    number_format((float) $order->subtotal_amount, 2, ',', ''),
                    number_format((float) $order->discount_amount, 2, ',', ''),
                    number_format((float) $order->shipping_cost, 2, ',', ''),
                    number_format((float) $order->total_amount, 2, ',', ''),
                    number_format((float) $order->cashback_total, 2, ',', ''),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function sanitizeCsvCell(?string $value): string
    {
        $text = (string) ($value ?? '');
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$text;
        }

        return $text;
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
}
