<?php

namespace App\Actions\Orders;

use App\Http\Controllers\VirtualCardController;
use App\Models\CardTransaction;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use App\Models\VirtualCard;
use App\Services\CartPricing;
use App\Services\WalletLedgerService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PlaceOrderAction
{
    public function __construct(
        private readonly CartPricing $pricing,
        private readonly WalletLedgerService $ledger,
    ) {}

    /**
     * Executa o checkout completo de forma transacional, idempotente e ordenada por ID.
     *
     * @param  array<int, array{product_id: int, quantity: int, price_snapshot?: float|null}>  $requestedItems
     * @return array{order: Order, duplicate: bool, price_warnings: list<string>}
     */
    public function execute(
        User $user,
        array $requestedItems,
        string $shippingAddress,
        ?string $shippingCep = null,
        ?string $cardPin = null,
        ?string $couponCode = null,
        ?string $idempotencyKey = null,
    ): array {
        if ($idempotencyKey) {
            $existingOrder = Order::where('idempotency_key', $idempotencyKey)
                ->where('user_id', $user->id)
                ->first();

            if ($existingOrder) {
                return [
                    'order' => $existingOrder,
                    'duplicate' => true,
                    'price_warnings' => [],
                ];
            }
        }

        if ($requestedItems === []) {
            throw ValidationException::withMessages([
                'cart' => 'Seu carrinho está vazio. Adicione produtos antes de finalizar a compra.',
            ]);
        }

        ksort($requestedItems, SORT_NUMERIC);

        $pinThrottleKey = VirtualCardController::pinThrottleKey((int) $user->id);

        if (RateLimiter::tooManyAttempts($pinThrottleKey, VirtualCardController::PIN_MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($pinThrottleKey);
            $minutes = (int) ceil($seconds / 60);

            throw ValidationException::withMessages([
                'card_pin' => "Muitas tentativas incorretas de PIN. Seu cartão foi temporariamente protegido por {$minutes} minuto(s) ({$seconds}s).",
            ]);
        }

        $initialCard = $user->virtualCard()->firstOrCreate([], [
            'card_number' => VirtualCard::generateUniqueCardNumber(),
            'cvv' => VirtualCard::generateCvv(),
            'expiration_date' => VirtualCard::DEFAULT_EXPIRATION_DATE,
            'balance' => 0.00,
            'cashback_earned' => 0.00,
        ]);

        if (! $initialCard->is_active) {
            throw ValidationException::withMessages([
                'card' => 'Seu cartão virtual está bloqueado. Desbloqueie-o para continuar.',
            ]);
        }

        if (empty($initialCard->security_pin)) {
            throw ValidationException::withMessages([
                'card_pin' => 'Por segurança, cadastre um PIN de 4 dígitos no seu Cartão Virtual antes de realizar a primeira compra.',
            ]);
        }

        if (! Hash::check((string) ($cardPin ?? ''), $initialCard->security_pin)) {
            RateLimiter::hit($pinThrottleKey, VirtualCardController::PIN_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'card_pin' => 'PIN inválido. Informe o PIN de 4 dígitos do cartão.',
            ]);
        }

        RateLimiter::clear($pinThrottleKey);

        $priceWarnings = [];
        $wasDuplicate = false;

        try {
            $order = DB::transaction(function () use (
                $user,
                $requestedItems,
                $shippingAddress,
                $shippingCep,
                $couponCode,
                $idempotencyKey,
                &$priceWarnings,
                &$wasDuplicate
            ): Order {
                if ($idempotencyKey) {
                    $existingInsideTx = Order::where('idempotency_key', $idempotencyKey)
                        ->where('user_id', $user->id)
                        ->first();

                    if ($existingInsideTx) {
                        $wasDuplicate = true;

                        return $existingInsideTx;
                    }
                }

                $card = VirtualCard::where('user_id', $user->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $card->is_active) {
                    throw ValidationException::withMessages([
                        'card' => 'Seu cartão virtual está bloqueado. Desbloqueie-o para continuar.',
                    ]);
                }

                $lockedProducts = collect();

                foreach ($requestedItems as $productId => $item) {
                    $product = Product::where('id', $productId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (! $product->is_active) {
                        throw ValidationException::withMessages([
                            'stock' => "O produto {$product->name} não está mais disponível.",
                        ]);
                    }

                    if ($product->stock < $item['quantity']) {
                        throw ValidationException::withMessages([
                            'stock' => "Estoque insuficiente para o produto: {$product->name}.",
                        ]);
                    }

                    $lockedProducts->put($product->id, $product);
                }

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
                $cardBalanceCents = CartPricing::toCents((float) $card->balance);

                if ($cardBalanceCents < $totalCents) {
                    throw ValidationException::withMessages([
                        'balance' => 'Saldo insuficiente no cartão virtual. Faça uma recarga via PIX ou envie um comprovante para continuar.',
                    ]);
                }

                if ($card->daily_limit !== null) {
                    $dailyLimitCents = CartPricing::toCents((float) $card->daily_limit);
                    $spentTodayCents = $this->ledger->dailySpentCents($card);

                    if (($spentTodayCents + $totalCents) > $dailyLimitCents) {
                        throw ValidationException::withMessages([
                            'card' => 'Esta compra excede o limite diário configurado para o seu cartão virtual.',
                        ]);
                    }
                }

                foreach ($summary['items'] as $line) {
                    /** @var Product $prod */
                    $prod = $line['product'];
                    $prod->decrement('stock', $line['quantity']);
                }

                $order = Order::create([
                    'user_id' => $user->id,
                    'idempotency_key' => $idempotencyKey,
                    'subtotal_amount' => $summary['subtotalAll'],
                    'discount_amount' => $summary['discountAmount'],
                    'coupon_code' => $summary['activeCoupon'],
                    'shipping_cost' => $summary['shippingCost'],
                    'total_amount' => $summary['total'],
                    'cashback_total' => $summary['cashbackTotal'],
                    'status' => Order::STATUS_REQUESTED,
                    'shipping_address' => $shippingAddress,
                    'shipping_cep' => $shippingCep,
                ]);

                $orderItemsData = [];
                foreach ($summary['items'] as $line) {
                    /** @var Product $lineProduct */
                    $lineProduct = $line['product'];
                    $orderItemsData[] = [
                        'product_id' => $lineProduct->id,
                        'product_name' => $lineProduct->name,
                        'product_sku' => 'PROD-'.str_pad((string) $lineProduct->id, 4, '0', STR_PAD_LEFT),
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'cashback_percent' => $line['cashback_percent'],
                        'discount_allocated' => $line['discount'],
                        'item_status' => Order::STATUS_REQUESTED,
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

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'from_status' => null,
                    'to_status' => Order::STATUS_REQUESTED,
                    'changed_by' => $user->id,
                    'notes' => 'Pedido realizado com saldo do Cartão Virtual.',
                    'created_at' => now(),
                ]);

                $this->ledger->debit(
                    lockedCard: $card,
                    amountCents: $totalCents,
                    type: CardTransaction::TYPE_PURCHASE,
                    description: 'Compra #PED-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
                    reference: $order,
                    idempotencyKey: "order:{$order->id}:purchase",
                );

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
                        'price_warnings' => [],
                    ];
                }
            }

            throw $e;
        }

        return [
            'order' => $order,
            'duplicate' => $wasDuplicate,
            'price_warnings' => $priceWarnings,
        ];
    }
}
