<?php

namespace App\Actions\Orders;

use App\Models\AuditLog;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\CartPricing;
use App\Services\InventoryService;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveOrderAction
{
    public function __construct(
        private readonly WalletLedgerService $ledger,
        private readonly InventoryService $inventory,
    ) {}

    public function execute(Order $order, User $approver): Order
    {
        return DB::transaction(function () use ($order, $approver): Order {
            $lockedOrder = Order::where('id', $order->id)
                ->with(['items', 'company'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== Order::STATUS_AWAITING_APPROVAL) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido não está aguardando aprovação.',
                ]);
            }

            $company = $lockedOrder->company;
            if (! $company || ! $approver->isCompanyManager($company)) {
                abort(403, 'Apenas um gestor da empresa pode aprovar este pedido.');
            }

            $wallet = CreditWallet::where('company_id', $company->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $wallet->is_active) {
                throw ValidationException::withMessages([
                    'wallet' => 'A carteira de créditos da empresa está bloqueada.',
                ]);
            }

            $requestedItems = [];
            foreach ($lockedOrder->items as $item) {
                $requestedItems[(int) $item->product_id] = [
                    'product_id' => (int) $item->product_id,
                    'quantity' => (int) $item->quantity,
                ];
            }

            $lockedProducts = $this->inventory->lockAndValidateProducts($requestedItems);
            $summaryItems = [];
            foreach ($lockedOrder->items as $item) {
                $summaryItems[] = [
                    'product' => $lockedProducts->get((int) $item->product_id),
                    'quantity' => (int) $item->quantity,
                ];
            }

            $totalCents = CartPricing::toCents((float) $lockedOrder->total_amount);
            if ($wallet->daily_limit !== null) {
                $dailyLimitCents = CartPricing::toCents((float) $wallet->daily_limit);
                $spentTodayCents = $this->ledger->dailySpentCents($wallet);
                if (($spentTodayCents + $totalCents) > $dailyLimitCents) {
                    throw ValidationException::withMessages([
                        'wallet' => 'A aprovação excede o limite diário configurado para a carteira da empresa.',
                    ]);
                }
            }

            $this->inventory->deductForOrder($lockedOrder, $summaryItems, (int) $approver->id);

            $this->ledger->debit(
                lockedWallet: $wallet,
                amountCents: $totalCents,
                type: CreditTransaction::TYPE_ORDER_DEBIT,
                description: 'Compra corporativa aprovada #PED-'.str_pad((string) $lockedOrder->id, 6, '0', STR_PAD_LEFT),
                reference: $lockedOrder,
                idempotencyKey: "order:{$lockedOrder->id}:debit",
                actorUserId: (int) $approver->id,
                reason: 'Débito de créditos na aprovação do pedido pelo gestor da empresa.',
            );

            $lockedOrder->update([
                'status' => Order::STATUS_REQUESTED,
                'credits_debited' => CartPricing::toDecimal($totalCents),
                'approved_by_user_id' => $approver->id,
                'approved_at' => now(),
            ]);

            $lockedOrder->items()->update(['item_status' => Order::STATUS_REQUESTED]);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'from_status' => Order::STATUS_AWAITING_APPROVAL,
                'to_status' => Order::STATUS_REQUESTED,
                'changed_by' => $approver->id,
                'notes' => "Pedido aprovado por {$approver->name} com débito de créditos.",
                'created_at' => now(),
            ]);

            AuditLog::record(
                'order.approved',
                $lockedOrder,
                ['status' => Order::STATUS_AWAITING_APPROVAL],
                ['status' => Order::STATUS_REQUESTED, 'approved_by' => $approver->id],
                $approver->id,
                $company->id
            );

            $this->ledger->notifyIfLowBalance($wallet->fresh(), $company);

            return $lockedOrder;
        });
    }
}
