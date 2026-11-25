<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliverOrderAction
{
    public function execute(
        Order $order,
        int $actorUserId,
        ?string $trackingCode = null,
        ?string $receiverName = null,
        ?string $carrier = null,
    ): bool {
        return DB::transaction(function () use ($order, $actorUserId, $trackingCode, $receiverName, $carrier): bool {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status === Order::STATUS_DELIVERED) {
                return false;
            }

            $currentStatus = $lockedOrder->statusEnum();
            if (! $currentStatus->canTransitionTo(OrderStatus::Delivered)) {
                throw ValidationException::withMessages([
                    'order' => 'Apenas pedidos confirmados em andamento ("Produto solicitado", "Em separação" ou "Enviado") podem ser marcados como entregues.',
                ]);
            }

            $previousStatus = $lockedOrder->status;
            $resolvedTracking = $trackingCode ?: $lockedOrder->tracking_code;

            $lockedOrder->update([
                'status' => Order::STATUS_DELIVERED,
                'delivered_at' => now(),
                'tracking_code' => $resolvedTracking,
            ]);

            $lockedOrder->items()->update([
                'item_status' => Order::STATUS_DELIVERED,
            ]);

            Delivery::updateOrCreate(
                ['order_id' => $lockedOrder->id],
                [
                    'status' => Delivery::STATUS_DELIVERED,
                    'carrier' => $carrier ?: 'Frota NexoSupri',
                    'tracking_code' => $resolvedTracking,
                    'receiver_name' => $receiverName ?: $lockedOrder->user?->name,
                    'delivered_at' => now(),
                ]
            );

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'from_status' => $previousStatus,
                'to_status' => Order::STATUS_DELIVERED,
                'changed_by' => $actorUserId,
                'notes' => 'Entrega confirmada'.(! empty($resolvedTracking) ? " (Rastreio: {$resolvedTracking})" : '').'.',
                'created_at' => now(),
            ]);

            AuditLog::record(
                'order.delivered',
                $lockedOrder,
                ['status' => $previousStatus],
                [
                    'status' => Order::STATUS_DELIVERED,
                    'tracking_code' => $resolvedTracking,
                    'receiver_name' => $receiverName,
                ],
                $actorUserId,
                $lockedOrder->company_id
            );

            return true;
        });
    }
}
