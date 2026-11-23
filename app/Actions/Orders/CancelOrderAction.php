<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\CardTransaction;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\VirtualCard;
use App\Services\CartPricing;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrderAction
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    public function execute(Order $order, int $actorUserId, string $notes, bool $customerSelfCancel = false): Order
    {
        return DB::transaction(function () use ($order, $actorUserId, $notes, $customerSelfCancel): Order {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($customerSelfCancel) {
                if ($lockedOrder->status !== Order::STATUS_REQUESTED) {
                    throw ValidationException::withMessages([
                        'order' => 'Somente pedidos com status "Produto solicitado" podem ser cancelados diretamente pelo cliente.',
                    ]);
                }
            } elseif (! $lockedOrder->statusEnum()->canTransitionTo(OrderStatus::CANCELLED)) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido não pode mais ser cancelado.',
                ]);
            }

            $previousStatus = $lockedOrder->status;
            $card = VirtualCard::where('user_id', $lockedOrder->user_id)->lockForUpdate()->firstOrFail();
            $refundCents = CartPricing::toCents((float) $lockedOrder->total_amount);

            foreach ($lockedOrder->items->sortBy('product_id') as $item) {
                Product::where('id', $item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->increment('stock', $item->quantity);
            }

            $this->ledger->credit(
                lockedCard: $card,
                amountCents: $refundCents,
                type: CardTransaction::TYPE_REFUND,
                description: 'Estorno do pedido #PED-'.str_pad((string) $lockedOrder->id, 6, '0', STR_PAD_LEFT),
                reference: $lockedOrder,
                idempotencyKey: "order:{$lockedOrder->id}:cancel-refund",
            );

            $redemptions = CouponRedemption::where('order_id', $lockedOrder->id)->get();
            foreach ($redemptions as $redemption) {
                $coupon = Coupon::where('id', $redemption->coupon_id)->lockForUpdate()->first();
                if ($coupon && $coupon->used_count > 0) {
                    $coupon->update(['used_count' => max(0, $coupon->used_count - 1)]);
                }
                $redemption->delete();
            }

            $lockedOrder->update(['status' => Order::STATUS_CANCELLED]);
            $lockedOrder->items()->update(['item_status' => Order::STATUS_CANCELLED]);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'from_status' => $previousStatus,
                'to_status' => Order::STATUS_CANCELLED,
                'changed_by' => $actorUserId,
                'notes' => $notes,
                'created_at' => now(),
            ]);

            AuditLog::record(
                'order.cancelled',
                $lockedOrder,
                ['status' => $previousStatus],
                ['status' => Order::STATUS_CANCELLED, 'refund_amount' => CartPricing::toDecimal($refundCents)],
                $actorUserId
            );

            return $lockedOrder;
        });
    }
}
