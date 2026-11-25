<?php

namespace App\Actions\Orders;

use App\Models\AuditLog;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\StockMovement;
use App\Services\CartPricing;
use App\Services\InventoryService;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessRegretReturnAction
{
    public function __construct(
        private readonly WalletLedgerService $ledger,
        private readonly InventoryService $inventory,
    ) {}

    public function execute(Order $order, int $actorUserId): Order
    {
        return DB::transaction(function () use ($order, $actorUserId): Order {
            $lockedOrder = Order::where('id', $order->id)->with('items')->lockForUpdate()->firstOrFail();

            if (! $lockedOrder->canRequestRegretReturn()) {
                throw ValidationException::withMessages([
                    'order' => 'A solicitação de devolução só está disponível para pedidos entregues há no máximo 7 dias corridos.',
                ]);
            }

            $previousStatus = $lockedOrder->status;
            $wallet = CreditWallet::where('company_id', $lockedOrder->company_id)->lockForUpdate()->firstOrFail();

            $this->inventory->restoreFromOrder(
                $lockedOrder,
                StockMovement::TYPE_ORDER_RETURN_IN,
                'Devolução do pedido',
                $actorUserId
            );

            $orderCode = '#PED-'.str_pad((string) $lockedOrder->id, 6, '0', STR_PAD_LEFT);
            $refundCents = CartPricing::toCents((float) ($lockedOrder->credits_debited > 0 ? $lockedOrder->credits_debited : $lockedOrder->total_amount));

            $this->ledger->credit(
                lockedWallet: $wallet,
                amountCents: $refundCents,
                type: CreditTransaction::TYPE_ORDER_RETURN_REFUND,
                description: "Estorno por devolução {$orderCode}",
                reference: $lockedOrder,
                idempotencyKey: "order:{$lockedOrder->id}:return-refund",
                actorUserId: $actorUserId,
                reason: 'Estorno integral em créditos por devolução registrada dentro do prazo contratual.',
            );

            $lockedOrder->update(['status' => Order::STATUS_RETURNED]);
            $lockedOrder->items()->update(['item_status' => Order::STATUS_RETURNED]);

            Delivery::where('order_id', $lockedOrder->id)->update(['status' => Delivery::STATUS_RETURNED]);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'from_status' => $previousStatus,
                'to_status' => Order::STATUS_RETURNED,
                'changed_by' => $actorUserId,
                'notes' => 'Devolução registrada pela empresa cliente dentro do prazo de 7 dias.',
                'created_at' => now(),
            ]);

            AuditLog::record(
                'order.returned',
                $lockedOrder,
                ['status' => $previousStatus],
                ['status' => Order::STATUS_RETURNED, 'refund_credits' => CartPricing::toDecimal($refundCents)],
                $actorUserId,
                $lockedOrder->company_id
            );

            return $lockedOrder;
        });
    }
}
