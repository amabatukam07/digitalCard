<?php

namespace App\Actions\Orders;

use App\Models\AuditLog;
use App\Models\CardTransaction;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\VirtualCard;
use App\Services\CartPricing;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessRegretReturnAction
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    public function execute(Order $order, int $actorUserId): Order
    {
        return DB::transaction(function () use ($order, $actorUserId): Order {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if (! $lockedOrder->canRequestRegretReturn()) {
                throw ValidationException::withMessages([
                    'order' => 'A solicitação de arrependimento só está disponível para pedidos entregues há no máximo 7 dias corridos.',
                ]);
            }

            $previousStatus = $lockedOrder->status;
            $card = VirtualCard::where('user_id', $lockedOrder->user_id)->lockForUpdate()->firstOrFail();

            foreach ($lockedOrder->items->sortBy('product_id') as $item) {
                Product::where('id', $item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->increment('stock', $item->quantity);
            }

            $orderCode = '#PED-'.str_pad((string) $lockedOrder->id, 6, '0', STR_PAD_LEFT);
            $refundCents = CartPricing::toCents((float) $lockedOrder->total_amount);
            $cashbackReversalCents = $lockedOrder->cashback_credited_at
                ? CartPricing::toCents((float) $lockedOrder->cashback_total)
                : 0;

            $this->ledger->credit(
                lockedCard: $card,
                amountCents: $refundCents,
                type: CardTransaction::TYPE_REFUND,
                description: "Estorno por arrependimento (7 dias) {$orderCode}",
                reference: $lockedOrder,
                idempotencyKey: "order:{$lockedOrder->id}:return-refund",
            );

            $netCreditCents = $refundCents;
            if ($cashbackReversalCents > 0) {
                $reversalTx = $this->ledger->debit(
                    lockedCard: $card,
                    amountCents: $cashbackReversalCents,
                    type: CardTransaction::TYPE_CASHBACK_REVERSAL,
                    description: "Reversão de cashback por devolução {$orderCode}",
                    reference: $lockedOrder,
                    idempotencyKey: "order:{$lockedOrder->id}:return-cb-reversal",
                    allowPartialToZero: true,
                    cashbackDeltaCents: -$cashbackReversalCents,
                );
                $netCreditCents = max(0, $refundCents - abs(CartPricing::toCents((float) $reversalTx->amount)));
            }

            $lockedOrder->update(['status' => Order::STATUS_RETURNED]);
            $lockedOrder->items()->update(['item_status' => Order::STATUS_RETURNED]);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'from_status' => $previousStatus,
                'to_status' => Order::STATUS_RETURNED,
                'changed_by' => $actorUserId,
                'notes' => 'Direito de arrependimento (7 dias) exercido pelo cliente.',
                'created_at' => now(),
            ]);

            AuditLog::record(
                'order.returned_regret',
                $lockedOrder,
                ['status' => $previousStatus],
                ['status' => Order::STATUS_RETURNED, 'net_refund' => CartPricing::toDecimal($netCreditCents)],
                $actorUserId
            );

            return $lockedOrder;
        });
    }
}
