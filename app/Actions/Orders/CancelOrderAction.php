<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\StockMovement;
use App\Services\CartPricing;
use App\Services\InventoryService;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrderAction
{
    public function __construct(
        private readonly WalletLedgerService $ledger,
        private readonly InventoryService $inventory,
    ) {}

    public function execute(Order $order, int $actorUserId, string $notes, bool $customerSelfCancel = false): Order
    {
        return DB::transaction(function () use ($order, $actorUserId, $notes, $customerSelfCancel): Order {
            $lockedOrder = Order::where('id', $order->id)->with('items')->lockForUpdate()->firstOrFail();

            if ($customerSelfCancel) {
                if (! in_array($lockedOrder->status, [Order::STATUS_AWAITING_APPROVAL, Order::STATUS_REQUESTED], true)) {
                    throw ValidationException::withMessages([
                        'order' => 'Somente pedidos em "Aguardando aprovação" ou "Produto solicitado" podem ser cancelados diretamente.',
                    ]);
                }
            } elseif (! $lockedOrder->statusEnum()->canTransitionTo(OrderStatus::Cancelled)) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido não pode mais ser cancelado.',
                ]);
            }

            $previousStatus = $lockedOrder->status;
            $refundCents = CartPricing::toCents((float) $lockedOrder->credits_debited);

            if ($previousStatus !== Order::STATUS_AWAITING_APPROVAL) {
                $this->inventory->restoreFromOrder(
                    $lockedOrder,
                    StockMovement::TYPE_ORDER_CANCEL_IN,
                    'Estorno de cancelamento do pedido',
                    $actorUserId
                );

                if ($refundCents > 0) {
                    $wallet = CreditWallet::where('company_id', $lockedOrder->company_id)->lockForUpdate()->firstOrFail();

                    $this->ledger->credit(
                        lockedWallet: $wallet,
                        amountCents: $refundCents,
                        type: CreditTransaction::TYPE_ORDER_REFUND,
                        description: 'Estorno do pedido #PED-'.str_pad((string) $lockedOrder->id, 6, '0', STR_PAD_LEFT),
                        reference: $lockedOrder,
                        idempotencyKey: "order:{$lockedOrder->id}:cancel-refund",
                        actorUserId: $actorUserId,
                        reason: $notes,
                    );
                }
            }

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
                ['status' => Order::STATUS_CANCELLED, 'refund_credits' => CartPricing::toDecimal($refundCents)],
                $actorUserId,
                $lockedOrder->company_id
            );

            return $lockedOrder;
        });
    }
}
