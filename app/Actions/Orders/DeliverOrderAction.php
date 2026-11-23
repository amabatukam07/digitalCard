<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\CardTransaction;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\VirtualCard;
use App\Services\CartPricing;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliverOrderAction
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    public function execute(Order $order, int $actorUserId, ?string $trackingCode = null): bool
    {
        return DB::transaction(function () use ($order, $actorUserId, $trackingCode): bool {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status === Order::STATUS_DELIVERED) {
                return false;
            }

            $currentStatus = $lockedOrder->statusEnum();
            if (! $currentStatus->canTransitionTo(OrderStatus::DELIVERED)) {
                throw ValidationException::withMessages([
                    'order' => 'Apenas pedidos em andamento ("Produto solicitado", "Em separação" ou "Enviado") podem ser marcados como entregues.',
                ]);
            }

            $previousStatus = $lockedOrder->status;
            $cashbackCents = CartPricing::toCents((float) $lockedOrder->cashback_total);
            $cashbackAmount = CartPricing::toDecimal($cashbackCents);
            $shouldCreditCashback = $lockedOrder->cashback_credited_at === null && $cashbackCents > 0;

            $lockedOrder->update([
                'status' => Order::STATUS_DELIVERED,
                'delivered_at' => now(),
                'cashback_credited_at' => $shouldCreditCashback ? now() : $lockedOrder->cashback_credited_at,
                'tracking_code' => $trackingCode ?? $lockedOrder->tracking_code,
            ]);

            $lockedOrder->items()->update([
                'item_status' => Order::STATUS_DELIVERED,
            ]);

            if ($shouldCreditCashback) {
                $card = VirtualCard::where('user_id', $lockedOrder->user_id)
                    ->lockForUpdate()
                    ->first();

                if ($card) {
                    $this->ledger->credit(
                        lockedCard: $card,
                        amountCents: $cashbackCents,
                        type: CardTransaction::TYPE_CASHBACK,
                        description: 'Cashback do pedido #PED-'.str_pad((string) $lockedOrder->id, 6, '0', STR_PAD_LEFT),
                        reference: $lockedOrder,
                        idempotencyKey: "order:{$lockedOrder->id}:cashback",
                        cashbackDeltaCents: $cashbackCents,
                    );
                }
            }

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'from_status' => $previousStatus,
                'to_status' => Order::STATUS_DELIVERED,
                'changed_by' => $actorUserId,
                'notes' => 'Entrega confirmada'.(! empty($trackingCode) ? " (Rastreio: {$trackingCode})" : '').'.',
                'created_at' => now(),
            ]);

            AuditLog::record(
                'order.delivered',
                $lockedOrder,
                ['status' => $previousStatus],
                [
                    'status' => Order::STATUS_DELIVERED,
                    'tracking_code' => $lockedOrder->tracking_code,
                    'cashback_credited' => $shouldCreditCashback ? $cashbackAmount : 0.0,
                ],
                $actorUserId
            );

            return true;
        });
    }
}
