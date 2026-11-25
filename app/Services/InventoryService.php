<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Valida, trava para atualização e baixa estoque para os itens do pedido.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $requestedItems
     * @return Collection<int, Product>
     */
    public function lockAndValidateProducts(array $requestedItems): Collection
    {
        ksort($requestedItems, SORT_NUMERIC);
        $lockedProducts = collect();

        foreach ($requestedItems as $productId => $item) {
            $product = Product::where('id', $productId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $product->is_active) {
                throw ValidationException::withMessages([
                    'stock' => "O produto {$product->name} não está mais disponível no catálogo.",
                ]);
            }

            if ($product->stock < $item['quantity']) {
                throw ValidationException::withMessages([
                    'stock' => "Estoque insuficiente para {$product->name}. Disponível: {$product->stock}.",
                ]);
            }

            $lockedProducts->put($product->id, $product);
        }

        return $lockedProducts;
    }

    /**
     * Baixa estoque e registra movimentações de saída de pedido.
     *
     * @param  list<array{product: Product, quantity: int}>  $summaryItems
     */
    public function deductForOrder(Order $order, array $summaryItems, int $actorUserId): void
    {
        $orderCode = '#PED-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);

        foreach ($summaryItems as $line) {
            /** @var Product $product */
            $product = $line['product'];
            $qty = (int) $line['quantity'];
            $before = (int) $product->stock;
            $after = max(0, $before - $qty);

            $product->update(['stock' => $after]);

            $this->recordMovement(
                product: $product,
                type: StockMovement::TYPE_ORDER_OUT,
                quantityDelta: -$qty,
                stockBefore: $before,
                stockAfter: $after,
                userId: $actorUserId,
                reference: $order,
                notes: "Saída por pedido {$orderCode}",
            );
        }
    }

    /**
     * Recompõe estoque de um pedido cancelado ou devolvido e registra movimentações.
     */
    public function restoreFromOrder(Order $order, string $movementType, string $reasonPrefix, int $actorUserId): void
    {
        $orderCode = '#PED-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);

        foreach ($order->items->sortBy('product_id') as $item) {
            $product = Product::where('id', $item->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            $qty = (int) $item->quantity;
            $before = (int) $product->stock;
            $after = $before + $qty;

            $product->update(['stock' => $after]);

            $this->recordMovement(
                product: $product,
                type: $movementType,
                quantityDelta: $qty,
                stockBefore: $before,
                stockAfter: $after,
                userId: $actorUserId,
                reference: $order,
                notes: "{$reasonPrefix} {$orderCode}",
            );
        }
    }

    public function recordMovement(
        Product $product,
        string $type,
        int $quantityDelta,
        int $stockBefore,
        int $stockAfter,
        ?int $userId = null,
        ?Model $reference = null,
        ?string $notes = null,
    ): StockMovement {
        return StockMovement::create([
            'product_id' => $product->id,
            'user_id' => $userId,
            'type' => $type,
            'quantity' => $quantityDelta,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference ? (int) $reference->getKey() : null,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }
}
