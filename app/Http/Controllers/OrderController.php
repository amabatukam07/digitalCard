<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CancelOrderAction;
use App\Actions\Orders\DeliverOrderAction;
use App\Actions\Orders\PlaceOrderAction;
use App\Actions\Orders\ProcessRegretReturnAction;
use App\Enums\OrderStatus;
use App\Http\Requests\CheckoutRequest;
use App\Models\AuditLog;
use App\Models\CartItem;
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
    /**
     * Finaliza a compra descontando do Cartão Virtual (S5, S9, S10, F2, F4).
     */
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
            cardPin: $validated['card_pin'] ?? null,
            couponCode: $activeCoupon,
            idempotencyKey: $idempotencyKey,
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

        $request->user()->notify(new OrderStatusUpdatedNotification($order, Order::STATUS_REQUESTED));

        $redirect = redirect()
            ->route('orders.show', $order)
            ->with('success', 'Compra realizada com sucesso! Status: Produto solicitado.');

        if ($result['price_warnings'] !== []) {
            $redirect->with('warning', implode(' ', $result['price_warnings']));
        }

        return $redirect;
    }

    public function myOrders(Request $request): View
    {
        $orders = $request->user()
            ->orders()
            ->with(['items.product', 'statusHistories'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('orders.index', compact('orders'));
    }

    public function show(Order $order): View
    {
        Gate::authorize('view', $order);
        $order->load(['items.product', 'user', 'statusHistories.changer']);

        return view('orders.show', compact('order'));
    }

    /**
     * Cancela um pedido pelo cliente enquanto estiver em "Produto solicitado".
     */
    public function cancelOrder(Request $request, Order $order, CancelOrderAction $cancelOrder): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        $cancelOrder->execute($order, (int) $request->user()->id, 'Cancelado pelo cliente.', customerSelfCancel: true);

        Cache::forget('admin_pending_counts');

        return back()->with('success', 'Pedido cancelado. O saldo foi estornado no cartão virtual e o estoque devolvido.');
    }

    /**
     * Solicitação de devolução por direito de arrependimento (até 7 dias após entrega).
     */
    public function requestRegretReturn(Request $request, Order $order, ProcessRegretReturnAction $processReturn): RedirectResponse
    {
        Gate::authorize('requestReturn', $order);

        $processReturn->execute($order, (int) $request->user()->id);

        Cache::forget('admin_pending_counts');
        $order->user?->notify(new OrderStatusUpdatedNotification($order->fresh(), Order::STATUS_RETURNED));

        return back()->with('success', 'Devolução por arrependimento (7 dias) concluída! O saldo foi estornado e o estoque recomposto.');
    }

    /**
     * Readiciona os itens disponíveis de um pedido anterior ao carrinho em 1 clique.
     */
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
            return back()->with('error', 'Os produtos deste pedido estão indisponíveis no momento.');
        }

        $request->session()->put('cart', $cart);

        $skippedNote = $skipped !== []
            ? ' (Indisponíveis ignorados: '.implode(', ', $skipped).')'
            : '';

        return redirect()->route('cart.index')->with(
            'success',
            "{$addedCount} ".($addedCount === 1 ? 'produto readicionado' : 'produtos readicionados').' ao carrinho a partir do pedido #PED-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT).'.'.$skippedNote
        );
    }

    public function adminIndex(Request $request): View
    {
        $query = Order::with(['user', 'items.product', 'statusHistories'])->latest();

        if ($statusFilter = trim((string) $request->input('status'))) {
            if (OrderStatus::tryFrom($statusFilter) !== null) {
                $query->where('status', $statusFilter);
            }
        }

        if ($search = trim((string) $request->input('q'))) {
            $escaped = addcslashes($search, '%_\\');
            $numericId = (int) preg_replace('/\D/', '', $search);

            $query->where(function ($builder) use ($escaped, $numericId): void {
                if ($numericId > 0) {
                    $builder->orWhere('id', $numericId);
                }

                $builder->orWhereRaw("tracking_code LIKE ? ESCAPE '\\'", ["%{$escaped}%"])
                    ->orWhereHas('user', function ($userQuery) use ($escaped): void {
                        $userQuery->whereRaw("name LIKE ? ESCAPE '\\'", ["%{$escaped}%"])
                            ->orWhereRaw("email LIKE ? ESCAPE '\\'", ["%{$escaped}%"]);
                    });
            });
        }

        $orders = $query->paginate(15)->withQueryString();

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
            ])->sum('total_amount'),
        ];

        return view('admin.orders.index', compact('orders', 'orderStats'));
    }

    /**
     * Permite ao administrador avançar o status do pedido na máquina de estados ou cancelar.
     */
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
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $targetStatus = OrderStatus::tryFrom($validated['status']);
        if (! $targetStatus) {
            throw ValidationException::withMessages(['status' => 'Status de pedido inválido.']);
        }

        if ($targetStatus === OrderStatus::DELIVERED) {
            return $this->markAsDelivered($request, $order, $deliverOrder);
        }

        if ($targetStatus === OrderStatus::CANCELLED) {
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

            $lockedOrder->update([
                'status' => $targetStatus->value,
                'tracking_code' => $validated['tracking_code'] ?? $lockedOrder->tracking_code,
            ]);

            $lockedOrder->items()->update(['item_status' => $targetStatus->value]);

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
                $request->user()->id
            );
        });

        Cache::forget('admin_pending_counts');
        $order->user?->notify(new OrderStatusUpdatedNotification($order->fresh(), $targetStatus->value));

        return back()->with('success', "Status do pedido atualizado para: {$targetStatus->value}.");
    }

    /**
     * Permite ao administrador cancelar um pedido em aberto com estorno integral.
     */
    public function adminCancelOrder(Request $request, Order $order, CancelOrderAction $cancelOrder): RedirectResponse
    {
        Gate::authorize('manage', Order::class);

        $cancelOrder->execute(
            $order,
            (int) $request->user()->id,
            'Cancelado administrativamente com estorno integral.',
            customerSelfCancel: false,
        );

        Cache::forget('admin_pending_counts');
        $order->user?->notify(new OrderStatusUpdatedNotification($order->fresh(), Order::STATUS_CANCELLED));

        return back()->with('success', 'Pedido cancelado pelo administrador. Saldo estornado e estoque devolvido.');
    }

    /**
     * Atualiza o status para "Produto entregue" e credita o cashback uma única vez (idempotente).
     */
    public function markAsDelivered(Request $request, Order $order, DeliverOrderAction $deliverOrder): RedirectResponse
    {
        Gate::authorize('manage', Order::class);

        $validated = $request->validate([
            'tracking_code' => ['nullable', 'string', 'max:100'],
        ]);

        $processed = $deliverOrder->execute(
            $order,
            (int) $request->user()->id,
            $validated['tracking_code'] ?? null,
        );

        if (! $processed) {
            return back()->with('success', 'Este pedido já consta como "Produto entregue".');
        }

        Cache::forget('admin_pending_counts');
        $order->user?->notify(new OrderStatusUpdatedNotification($order->fresh(), Order::STATUS_DELIVERED));

        return back()->with('success', 'Status atualizado para: Produto entregue. Cashback creditado no cartão do cliente.');
    }
}
