<?php

namespace App\Http\Controllers;

use App\Actions\Orders\ApproveOrderAction;
use App\Actions\Orders\CancelOrderAction;
use App\Actions\Orders\DeliverOrderAction;
use App\Actions\Orders\PlaceOrderAction;
use App\Actions\Orders\ProcessRegretReturnAction;
use App\Enums\OrderStatus;
use App\Http\Requests\CheckoutRequest;
use App\Models\AuditLog;
use App\Models\CartItem;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Notifications\OrderStatusUpdatedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function checkout(CheckoutRequest $request, PlaceOrderAction $placeOrder): RedirectResponse
    {
        $validated = $request->validated();
        $idempotencyKey = $validated['idempotency_key'] ?? null;

        $request->session()->put('shipping_address', $validated['shipping_address']);
        if (! empty($validated['shipping_cep'])) {
            $request->session()->put('shipping_cep', $validated['shipping_cep']);
        }

        $isExpressCheckout = isset($validated['items']);
        $sessionCart = $request->session()->get('cart', []);
        $requestedItems = collect($validated['items'] ?? $sessionCart)
            ->map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'price_snapshot' => $item['price_snapshot'] ?? ($sessionCart[$item['product_id']]['price_snapshot'] ?? null),
            ])
            ->groupBy('product_id')
            ->map(fn ($items, $productId): array => [
                'product_id' => (int) $productId,
                'quantity' => (int) $items->sum('quantity'),
                'price_snapshot' => $items->first()['price_snapshot'] ?? null,
            ])
            ->keyBy('product_id')
            ->all();

        $activeCoupon = ! $isExpressCheckout ? $request->session()->get('cart_coupon') : null;
        $effectiveCep = $validated['shipping_cep'] ?? $request->session()->get('shipping_cep');

        $result = $placeOrder->execute(
            user: $request->user(),
            requestedItems: $requestedItems,
            shippingAddress: $validated['shipping_address'],
            shippingCep: $effectiveCep,
            couponCode: $activeCoupon,
            idempotencyKey: $idempotencyKey,
            department: $validated['department'] ?? null,
            notes: $validated['notes'] ?? null,
        );

        $order = $result['order'];

        if ($result['duplicate']) {
            return redirect()
                ->route('orders.show', $order)
                ->with('warning', 'Este pedido já foi processado anteriormente (proteção contra clique duplo).');
        }

        $request->session()->forget('checkout_token');
        if (! $isExpressCheckout) {
            $request->session()->forget(['cart', 'cart_coupon']);
            CartItem::where('user_id', $request->user()->id)->delete();
        }

        Cache::forget('admin_pending_counts');

        $request->user()->notify(new OrderStatusUpdatedNotification($order, $order->status));

        $successMessage = $result['awaiting_approval']
            ? 'Pedido enviado para aprovação do gestor da empresa! Os créditos serão debitados após aprovação.'
            : 'Pedido confirmado com débito em créditos! Status: Produto solicitado.';

        $redirect = redirect()
            ->route('orders.show', $order)
            ->with('success', $successMessage);

        if ($result['price_warnings'] !== []) {
            $redirect->with('warning', implode(' ', $result['price_warnings']));
        }

        return $redirect;
    }

    public function myOrders(Request $request): View
    {
        $user = $request->user();
        $company = $user->primaryCompany();

        $orders = Order::query()
            ->with(['user', 'approver', 'items.product', 'statusHistories', 'delivery'])
            ->where(function ($query) use ($user, $company): void {
                if ($company) {
                    $query->where('company_id', $company->id);
                } else {
                    $query->where('user_id', $user->id);
                }
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $isManager = $company ? $user->isCompanyManager($company) : false;

        return view('orders.index', compact('orders', 'company', 'isManager'));
    }

    public function show(Request $request, Order $order): View
    {
        Gate::authorize('view', $order);
        $order->load(['company', 'items.product', 'user', 'approver', 'statusHistories.changer', 'delivery']);

        $canApprove = Gate::allows('approve', $order);

        return view('orders.show', compact('order', 'canApprove'));
    }

    public function approveOrder(Request $request, Order $order, ApproveOrderAction $approveOrder): RedirectResponse
    {
        Gate::authorize('approve', $order);

        $approved = $approveOrder->execute($order, $request->user());

        Cache::forget('admin_pending_counts');
        $approved->user?->notify(new OrderStatusUpdatedNotification($approved, Order::STATUS_REQUESTED));

        return back()->with('success', 'Pedido aprovado! Créditos debitados da carteira da empresa e encaminhado para separação.');
    }

    public function cancelOrder(Request $request, Order $order, CancelOrderAction $cancelOrder): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        $cancelOrder->execute($order, (int) $request->user()->id, 'Cancelado pela empresa cliente.', customerSelfCancel: true);

        Cache::forget('admin_pending_counts');

        return back()->with('success', 'Pedido cancelado. Créditos estornados na carteira da empresa e estoque recomposto.');
    }

    public function requestRegretReturn(Request $request, Order $order, ProcessRegretReturnAction $processReturn): RedirectResponse
    {
        Gate::authorize('requestReturn', $order);

        $processReturn->execute($order, (int) $request->user()->id);

        Cache::forget('admin_pending_counts');
        $order->user?->notify(new OrderStatusUpdatedNotification($order->fresh(), Order::STATUS_RETURNED));

        return back()->with('success', 'Devolução registrada com sucesso! Os créditos foram estornados na carteira da empresa.');
    }

    public function reorder(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('view', $order);
        $order->load('items.product');

        $cart = $request->session()->get('cart', []);
        $addedCount = 0;
        $skipped = [];

        foreach ($order->items as $item) {
            $product = $item->product;
            if (! $product || ! $product->is_active || $product->stock < 1) {
                $skipped[] = $item->display_name;

                continue;
            }

            $currentQty = (int) ($cart[$product->id]['quantity'] ?? 0);
            $targetQty = min($product->stock, $currentQty + max(1, (int) $item->quantity));

            $cart[$product->id] = [
                'product_id' => $product->id,
                'quantity' => $targetQty,
                'price_snapshot' => (float) $product->price,
            ];

            CartItem::updateOrCreate(
                ['user_id' => $request->user()->id, 'product_id' => $product->id],
                ['quantity' => $targetQty, 'price_snapshot' => (float) $product->price]
            );

            $addedCount++;
        }

        if ($addedCount === 0) {
            return back()->with('error', 'Os insumos deste pedido estão indisponíveis no momento.');
        }

        $request->session()->put('cart', $cart);

        $skippedNote = $skipped !== []
            ? ' (Indisponíveis ignorados: '.implode(', ', $skipped).')'
            : '';

        return redirect()->route('cart.index')->with(
            'success',
            "{$addedCount} ".($addedCount === 1 ? 'insumo readicionado' : 'insumos readicionados').' ao carrinho a partir do pedido #PED-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT).'.'.$skippedNote
        );
    }

    public function adminIndex(Request $request): View
    {
        abort_unless((bool) $request->user()?->hasAdminAccess(), 403);

        $query = Order::with(['company', 'user', 'items.product', 'statusHistories', 'delivery'])->latest();

        if ($statusFilter = trim((string) $request->input('status'))) {
            if (OrderStatus::tryFrom($statusFilter) !== null) {
                $query->where('status', $statusFilter);
            }
        }

        if ($search = trim((string) $request->input('q'))) {
            $like = '%'.$search.'%';
            $numericId = (int) preg_replace('/\D/', '', $search);

            $query->where(function ($builder) use ($like, $numericId): void {
                if ($numericId > 0) {
                    $builder->orWhere('id', $numericId);
                }

                $builder->orWhere('tracking_code', 'like', $like)
                    ->orWhereHas('company', function ($companyQuery) use ($like): void {
                        $companyQuery->where('trade_name', 'like', $like)
                            ->orWhere('cnpj', 'like', $like);
                    })
                    ->orWhereHas('user', function ($userQuery) use ($like): void {
                        $userQuery->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
            });
        }

        $currentStatus = trim((string) $request->input('status', ''));
        $search = trim((string) $request->input('q', ''));

        $orders = $query->paginate(15)->withQueryString();

        $statusCountsRaw = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total)
            ->all();
        $totalOrdersCount = array_sum($statusCountsRaw);

        $orderStats = [
            'pendingCount' => Order::whereIn('status', [
                Order::STATUS_REQUESTED,
                Order::STATUS_SEPARATING,
                Order::STATUS_SHIPPED,
            ])->count(),
            'deliveredCount' => Order::where('status', Order::STATUS_DELIVERED)->count(),
            'netSales' => (float) Order::whereNotIn('status', [
                Order::STATUS_CANCELLED,
                Order::STATUS_RETURNED,
                Order::STATUS_AWAITING_APPROVAL,
            ])->sum('total_amount'),
        ];

        return view('admin.orders.index', compact(
            'orders',
            'orderStats',
            'statusCountsRaw',
            'totalOrdersCount',
            'currentStatus',
            'search',
        ));
    }

    public function updateStatus(
        Request $request,
        Order $order,
        DeliverOrderAction $deliverOrder,
        CancelOrderAction $cancelOrder,
    ): RedirectResponse {
        Gate::authorize('manage', Order::class);

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'tracking_code' => ['nullable', 'string', 'max:100'],
            'carrier' => ['nullable', 'string', 'max:100'],
            'receiver_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $targetStatus = OrderStatus::tryFrom($validated['status']);
        if (! $targetStatus) {
            throw ValidationException::withMessages(['status' => 'Status de pedido inválido.']);
        }

        if ($targetStatus === OrderStatus::Delivered) {
            return $this->markAsDelivered($request, $order, $deliverOrder);
        }

        if ($targetStatus === OrderStatus::Cancelled) {
            return $this->adminCancelOrder($request, $order, $cancelOrder);
        }

        DB::transaction(function () use ($request, $order, $targetStatus, $validated): void {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
            $currentStatus = $lockedOrder->statusEnum();

            if (! $currentStatus->canTransitionTo($targetStatus)) {
                throw ValidationException::withMessages([
                    'order' => "Transição inválida de \"{$currentStatus->value}\" para \"{$targetStatus->value}\".",
                ]);
            }

            $trackingCode = $validated['tracking_code'] ?? $lockedOrder->tracking_code;

            $lockedOrder->update([
                'status' => $targetStatus->value,
                'tracking_code' => $trackingCode,
            ]);

            $lockedOrder->items()->update(['item_status' => $targetStatus->value]);

            if (in_array($targetStatus, [OrderStatus::Separating, OrderStatus::Shipped], true)) {
                Delivery::updateOrCreate(
                    ['order_id' => $lockedOrder->id],
                    [
                        'status' => $targetStatus === OrderStatus::Shipped ? Delivery::STATUS_IN_TRANSIT : Delivery::STATUS_SCHEDULED,
                        'carrier' => $validated['carrier'] ?? 'Frota NexoSupri',
                        'tracking_code' => $trackingCode,
                        'dispatched_at' => $targetStatus === OrderStatus::Shipped ? now() : null,
                    ]
                );
            }

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'from_status' => $currentStatus->value,
                'to_status' => $targetStatus->value,
                'changed_by' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
            ]);

            AuditLog::record(
                'order.status_updated',
                $lockedOrder,
                ['status' => $currentStatus->value],
                ['status' => $targetStatus->value, 'tracking_code' => $lockedOrder->tracking_code],
                $request->user()->id,
                $lockedOrder->company_id
            );
        });

        Cache::forget('admin_pending_counts');
        $order->user?->notify(new OrderStatusUpdatedNotification($order->fresh(), $targetStatus->value));

        return back()->with('success', "Status do pedido atualizado para: {$targetStatus->value}.");
    }

    public function adminCancelOrder(Request $request, Order $order, CancelOrderAction $cancelOrder): RedirectResponse
    {
        Gate::authorize('manage', Order::class);

        $cancelOrder->execute(
            $order,
            (int) $request->user()->id,
            'Cancelado administrativamente com estorno integral de créditos.',
            customerSelfCancel: false,
        );

        Cache::forget('admin_pending_counts');
        $order->user?->notify(new OrderStatusUpdatedNotification($order->fresh(), Order::STATUS_CANCELLED));

        return back()->with('success', 'Pedido cancelado pelo backoffice. Créditos estornados e estoque recomposto.');
    }

    public function markAsDelivered(Request $request, Order $order, DeliverOrderAction $deliverOrder): RedirectResponse
    {
        Gate::authorize('manage', Order::class);

        $validated = $request->validate([
            'tracking_code' => ['nullable', 'string', 'max:100'],
            'receiver_name' => ['nullable', 'string', 'max:120'],
            'carrier' => ['nullable', 'string', 'max:100'],
        ]);

        $processed = $deliverOrder->execute(
            $order,
            (int) $request->user()->id,
            $validated['tracking_code'] ?? null,
            $validated['receiver_name'] ?? null,
            $validated['carrier'] ?? null,
        );

        if (! $processed) {
            return back()->with('success', 'Este pedido já consta como "Produto entregue".');
        }

        Cache::forget('admin_pending_counts');
        $order->user?->notify(new OrderStatusUpdatedNotification($order->fresh(), Order::STATUS_DELIVERED));

        return back()->with('success', 'Status atualizado para: Produto entregue e comprovante logístico registrado.');
    }
}
