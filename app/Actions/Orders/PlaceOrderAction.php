<?php

namespace App\Actions\Orders;

use App\Models\AuditLog;
use App\Models\CompanyUser;
use App\Models\CouponRedemption;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Notifications\OrderApprovalRequestedNotification;
use App\Services\CartPricing;
use App\Services\InventoryService;
use App\Services\WalletLedgerService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceOrderAction
{
    public function __construct(
        private readonly CartPricing $pricing,
        private readonly WalletLedgerService $ledger,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * Executa o checkout B2B de forma transacional, idempotente e respeitando alçada de aprovação da empresa.
     *
     * @param  array<int, array{product_id: int, quantity: int, price_snapshot?: float|null}>  $requestedItems
     * @return array{order: Order, duplicate: bool, awaiting_approval: bool, price_warnings: list<string>}
     */
    public function execute(
        User $user,
        array $requestedItems,
        string $shippingAddress,
        ?string $shippingCep = null,
        ?string $couponCode = null,
        ?string $idempotencyKey = null,
        ?string $department = null,
        ?string $notes = null,
    ): array {
        if ($idempotencyKey) {
            $existingOrder = Order::where('idempotency_key', $idempotencyKey)
                ->where('user_id', $user->id)
                ->first();

            if ($existingOrder) {
                return [
                    'order' => $existingOrder,
                    'duplicate' => true,
                    'awaiting_approval' => $existingOrder->status === Order::STATUS_AWAITING_APPROVAL,
                    'price_warnings' => [],
                ];
            }
        }

        if ($requestedItems === []) {
            throw ValidationException::withMessages([
                'cart' => 'Seu carrinho está vazio. Adicione insumos antes de finalizar o pedido.',
            ]);
        }

        $company = $user->primaryCompany();
        if (! $company || ! $company->isActive()) {
            throw ValidationException::withMessages([
                'company' => 'Sua conta precisa estar vinculada a uma empresa cliente ativa para realizar pedidos.',
            ]);
        }

        $membership = $user->companyMembership($company);
        if (! $membership || ! $membership->is_active) {
            throw ValidationException::withMessages([
                'company' => 'Seu vínculo com a empresa está inativo.',
            ]);
        }

        ksort($requestedItems, SORT_NUMERIC);

        $priceWarnings = [];
        $wasDuplicate = false;
        $needsApproval = false;

        try {
            $order = DB::transaction(function () use (
                $user,
                $company,
                $membership,
                $requestedItems,
                $shippingAddress,
                $shippingCep,
                $couponCode,
                $idempotencyKey,
                $department,
                $notes,
                &$priceWarnings,
                &$wasDuplicate,
                &$needsApproval,
            ): Order {
                if ($idempotencyKey) {
                    $existingInsideTx = Order::where('idempotency_key', $idempotencyKey)
                        ->where('user_id', $user->id)
                        ->first();

                    if ($existingInsideTx) {
                        $wasDuplicate = true;
                        $needsApproval = $existingInsideTx->status === Order::STATUS_AWAITING_APPROVAL;

                        return $existingInsideTx;
                    }
                }

                $wallet = CreditWallet::where('company_id', $company->id)
                    ->lockForUpdate()
                    ->first();

                if (! $wallet) {
                    $wallet = $company->wallet()->create([
                        'balance' => 0.00,
                        'reserved_balance' => 0.00,
                        'total_acquired' => 0.00,
                        'total_spent' => 0.00,
                        'is_active' => true,
                    ]);
                }

                if (! $wallet->is_active) {
                    throw ValidationException::withMessages([
                        'wallet' => 'A carteira de créditos da empresa está bloqueada no momento.',
                    ]);
                }

                $lockedProducts = $this->inventory->lockAndValidateProducts($requestedItems);

                $summary = $this->pricing->calculate(
                    rawCart: $requestedItems,
                    couponCode: $couponCode,
                    user: $user,
                    cep: $shippingCep,
                    preloadedProducts: $lockedProducts,
                    lockCoupon: true,
                );

                $priceWarnings = $summary['price_warnings'];
                $totalCents = $summary['total_cents'];
                $totalAmount = $summary['total'];
                $walletBalanceCents = CartPricing::toCents((float) $wallet->balance);

                if ($walletBalanceCents < $totalCents) {
                    throw ValidationException::withMessages([
                        'balance' => 'Saldo insuficiente na carteira de créditos da empresa. Contrate um plano ou recarga de créditos para continuar.',
                    ]);
                }

                if ($wallet->daily_limit !== null) {
                    $dailyLimitCents = CartPricing::toCents((float) $wallet->daily_limit);
                    $spentTodayCents = $this->ledger->dailySpentCents($wallet);

                    if (($spentTodayCents + $totalCents) > $dailyLimitCents) {
                        throw ValidationException::withMessages([
                            'wallet' => 'Este pedido excede o limite diário configurado para a carteira da empresa.',
                        ]);
                    }
                }

                $isManager = $membership->isManager();
                $exceedsUserLimit = $membership->spending_limit !== null
                    && $totalCents > CartPricing::toCents((float) $membership->spending_limit);

                $needsApproval = ! $isManager && ($company->require_order_approval || $exceedsUserLimit);

                $initialStatus = $needsApproval ? Order::STATUS_AWAITING_APPROVAL : Order::STATUS_REQUESTED;

                $order = Order::create([
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'approved_by_user_id' => $needsApproval ? null : $user->id,
                    'approved_at' => $needsApproval ? null : now(),
                    'idempotency_key' => $idempotencyKey,
                    'subtotal_amount' => $summary['subtotalAll'],
                    'discount_amount' => $summary['discountAmount'],
                    'coupon_code' => $summary['activeCoupon'],
                    'shipping_cost' => $summary['shippingCost'],
                    'total_amount' => $totalAmount,
                    'credits_debited' => $needsApproval ? 0.00 : $totalAmount,
                    'total_cost_snapshot' => $summary['totalCost'],
                    'estimated_das_amount' => $summary['estimatedDas'],
                    'estimated_margin_amount' => $summary['estimatedMargin'],
                    'status' => $initialStatus,
                    'shipping_address' => $shippingAddress,
                    'shipping_cep' => $shippingCep,
                    'department' => $department ?: $membership->department,
                    'notes' => $notes,
                ]);

                $orderItemsData = [];
                foreach ($summary['items'] as $line) {
                    $lineProduct = $line['product'];
                    $orderItemsData[] = [
                        'product_id' => $lineProduct->id,
                        'product_name' => $lineProduct->name,
                        'product_sku' => $lineProduct->sku ?: ('NX-'.str_pad((string) $lineProduct->id, 4, '0', STR_PAD_LEFT)),
                        'unit_presentation' => $lineProduct->presentation ?: $lineProduct->unit,
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'unit_cost_snapshot' => $line['unit_cost'],
                        'das_rate_snapshot' => $line['das_rate'],
                        'discount_allocated' => $line['discount'],
                        'item_status' => $initialStatus,
                    ];
                }

                $order->items()->createMany($orderItemsData);

                if ($summary['coupon']) {
                    $summary['coupon']->increment('used_count');
                    CouponRedemption::create([
                        'coupon_id' => $summary['coupon']->id,
                        'user_id' => $user->id,
                        'order_id' => $order->id,
                        'discount_amount' => $summary['discountAmount'],
                    ]);
                }

                if ($needsApproval) {
                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'from_status' => null,
                        'to_status' => Order::STATUS_AWAITING_APPROVAL,
                        'changed_by' => $user->id,
                        'notes' => 'Pedido criado pelo solicitante e enviado para aprovação do gestor da empresa.',
                        'created_at' => now(),
                    ]);

                    AuditLog::record(
                        'order.requested_approval',
                        $order,
                        null,
                        ['status' => Order::STATUS_AWAITING_APPROVAL, 'total_amount' => $totalAmount],
                        $user->id,
                        $company->id
                    );

                    return $order;
                }

                $this->inventory->deductForOrder($order, $summary['items'], (int) $user->id);

                $this->ledger->debit(
                    lockedWallet: $wallet,
                    amountCents: $totalCents,
                    type: CreditTransaction::TYPE_ORDER_DEBIT,
                    description: 'Compra corporativa #PED-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
                    reference: $order,
                    idempotencyKey: "order:{$order->id}:debit",
                    actorUserId: (int) $user->id,
                    reason: 'Débito de créditos (1 crédito = R$ 1,00) na confirmação do pedido.',
                );

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'from_status' => null,
                    'to_status' => Order::STATUS_REQUESTED,
                    'changed_by' => $user->id,
                    'notes' => 'Pedido confirmado com débito na carteira de créditos da empresa.',
                    'created_at' => now(),
                ]);

                AuditLog::record(
                    'order.placed',
                    $order,
                    null,
                    ['status' => Order::STATUS_REQUESTED, 'credits_debited' => $totalAmount],
                    $user->id,
                    $company->id
                );

                $this->ledger->notifyIfLowBalance($wallet->fresh(), $company);

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($idempotencyKey) {
                $existingOrder = Order::where('idempotency_key', $idempotencyKey)
                    ->where('user_id', $user->id)
                    ->first();

                if ($existingOrder) {
                    return [
                        'order' => $existingOrder,
                        'duplicate' => true,
                        'awaiting_approval' => $existingOrder->status === Order::STATUS_AWAITING_APPROVAL,
                        'price_warnings' => [],
                    ];
                }
            }

            throw $e;
        }

        if (! $wasDuplicate && $needsApproval) {
            $managers = $company->users()
                ->wherePivot('company_role', CompanyUser::ROLE_MANAGER)
                ->wherePivot('is_active', true)
                ->get();

            foreach ($managers as $manager) {
                $manager->notify(new OrderApprovalRequestedNotification($order, $user->name));
            }
        }

        return [
            'order' => $order,
            'duplicate' => $wasDuplicate,
            'awaiting_approval' => $needsApproval,
            'price_warnings' => $priceWarnings,
        ];
    }
}
