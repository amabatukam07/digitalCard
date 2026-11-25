<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\InventoryService;
use App\Services\PricingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminSupplierController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless((bool) $request->user()?->hasAdminAccess(), 403);

        $suppliers = Supplier::with(['supplierProducts.product'])
            ->withCount('purchaseOrders')
            ->orderBy('trade_name')
            ->get();
        $products = Product::orderBy('name')->get();
        $purchaseOrders = PurchaseOrder::with(['supplier', 'items.product', 'creator'])
            ->latest()
            ->paginate(12, ['*'], 'po_page')
            ->withQueryString();
        $stockMovements = StockMovement::with(['product', 'user'])
            ->latest('created_at')
            ->latest('id')
            ->limit(25)
            ->get();

        $supplierStats = [
            'suppliersCount' => $suppliers->count(),
            'openPoCount' => PurchaseOrder::where('status', PurchaseOrder::STATUS_ORDERED)->count(),
            'receivedPoCount' => PurchaseOrder::where('status', PurchaseOrder::STATUS_RECEIVED)->count(),
            'lowStockCount' => Product::where('is_active', true)->whereColumn('stock', '<=', 'min_stock')->count(),
        ];

        return view('admin.suppliers.index', compact(
            'suppliers',
            'products',
            'purchaseOrders',
            'stockMovements',
            'supplierStats',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManagePurchasing(), 403);

        $validated = $request->validate([
            'cnpj' => ['required', 'string', 'max:18', 'unique:suppliers,cnpj'],
            'legal_name' => ['required', 'string', 'max:180'],
            'trade_name' => ['required', 'string', 'max:180'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:30'],
            'payment_terms' => ['nullable', 'string', 'max:120'],
            'lead_time_days' => ['required', 'integer', 'min:1', 'max:180'],
        ]);

        $supplier = Supplier::create(array_merge($validated, ['is_active' => true]));

        AuditLog::record(
            'supplier.created',
            $supplier,
            null,
            ['cnpj' => $supplier->cnpj, 'trade_name' => $supplier->trade_name],
            $request->user()->id
        );

        return back()->with('success', "Fornecedor \"{$supplier->trade_name}\" cadastrado com sucesso.");
    }

    public function linkProduct(Request $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManagePurchasing(), 403);

        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'product_id' => ['required', 'exists:products,id'],
            'supplier_sku' => ['nullable', 'string', 'max:60'],
            'supplier_cost' => ['required', 'numeric', 'min:0.01'],
            'lead_time_days' => ['required', 'integer', 'min:1', 'max:180'],
        ]);

        $link = SupplierProduct::updateOrCreate(
            [
                'supplier_id' => (int) $validated['supplier_id'],
                'product_id' => (int) $validated['product_id'],
            ],
            [
                'supplier_sku' => $validated['supplier_sku'] ?? null,
                'supplier_cost' => (float) $validated['supplier_cost'],
                'lead_time_days' => (int) $validated['lead_time_days'],
                'last_quoted_at' => now(),
            ]
        );

        AuditLog::record(
            'supplier.product_quoted',
            $link,
            null,
            ['supplier_cost' => (float) $link->supplier_cost],
            $request->user()->id
        );

        return back()->with('success', 'Cotação do fornecedor vinculada ao insumo.');
    }

    public function storePurchaseOrder(Request $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManagePurchasing(), 403);

        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_cost' => ['required', 'numeric', 'min:0.01'],
            'freight_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $freight = round((float) ($validated['freight_amount'] ?? 0.0), 2);
        $qty = (int) $validated['quantity'];
        $unitCost = round((float) $validated['unit_cost'], 2);
        $totalAmount = round(($unitCost * $qty) + $freight, 2);
        $freightPerUnit = $qty > 0 ? round($freight / $qty, 2) : 0.0;

        $po = DB::transaction(function () use ($validated, $request, $freight, $qty, $unitCost, $totalAmount, $freightPerUnit): PurchaseOrder {
            $order = PurchaseOrder::create([
                'supplier_id' => (int) $validated['supplier_id'],
                'created_by' => $request->user()->id,
                'status' => PurchaseOrder::STATUS_ORDERED,
                'total_amount' => $totalAmount,
                'freight_amount' => $freight,
                'ordered_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'product_id' => (int) $validated['product_id'],
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'inbound_freight_allocated' => $freightPerUnit,
            ]);

            return $order;
        });

        AuditLog::record(
            'purchase_order.created',
            $po,
            null,
            ['supplier_id' => $po->supplier_id, 'total_amount' => (float) $po->total_amount],
            $request->user()->id
        );

        return back()->with('success', 'Pedido de compra #OC-'.str_pad((string) $po->id, 5, '0', STR_PAD_LEFT).' emitido para o fornecedor.');
    }

    public function receivePurchaseOrder(
        Request $request,
        PurchaseOrder $purchaseOrder,
        InventoryService $inventory,
        PricingCalculator $calculator,
    ): RedirectResponse {
        abort_unless((bool) $request->user()?->canManagePurchasing(), 403);

        $validated = $request->validate([
            'invoice_number' => ['required', 'string', 'max:60'],
            'update_product_cost' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $purchaseOrder, $validated, $inventory, $calculator): void {
            $lockedPo = PurchaseOrder::where('id', $purchaseOrder->id)
                ->with('items')
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPo->status === PurchaseOrder::STATUS_RECEIVED) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'Este pedido de compra já foi recebido.',
                ]);
            }

            if ($lockedPo->status === PurchaseOrder::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'Pedido de compra cancelado não pode ser recebido.',
                ]);
            }

            $updateCost = $request->boolean('update_product_cost', true);

            foreach ($lockedPo->items as $item) {
                $product = Product::where('id', $item->product_id)->lockForUpdate()->firstOrFail();
                $before = (int) $product->stock;
                $after = $before + (int) $item->quantity;

                $productAttributes = ['stock' => $after];
                if ($updateCost) {
                    $newCost = round((float) $item->unit_cost, 2);
                    $newFreight = round((float) $item->inbound_freight_allocated, 2);
                    $productAttributes['cost_price'] = $newCost;
                    $productAttributes['inbound_freight'] = $newFreight;

                    $totalUnitCost = $calculator->totalUnitCost($newCost, $newFreight, (float) $product->other_costs);
                    $suggestedSale = $calculator->suggestedPrice(
                        $totalUnitCost,
                        (float) $product->markup_percent,
                        (float) $product->das_rate
                    );

                    ProductPrice::create([
                        'product_id' => $product->id,
                        'cost_price' => $newCost,
                        'inbound_freight' => $newFreight,
                        'other_costs' => $product->other_costs,
                        'markup_percent' => $product->markup_percent,
                        'das_rate' => $product->das_rate,
                        'sale_price' => $suggestedSale,
                        'changed_by' => $request->user()->id,
                        'reason' => "Atualização de custo na entrada NF {$validated['invoice_number']}",
                        'effective_at' => now(),
                    ]);
                }

                $product->update($productAttributes);

                $inventory->recordMovement(
                    product: $product,
                    type: StockMovement::TYPE_PURCHASE_IN,
                    quantityDelta: (int) $item->quantity,
                    stockBefore: $before,
                    stockAfter: $after,
                    userId: $request->user()->id,
                    reference: $lockedPo,
                    notes: "Recebimento #OC-{$lockedPo->id} (NF: {$validated['invoice_number']})",
                );
            }

            $lockedPo->update([
                'status' => PurchaseOrder::STATUS_RECEIVED,
                'invoice_number' => $validated['invoice_number'],
                'received_at' => now(),
            ]);

            AuditLog::record(
                'purchase_order.received',
                $lockedPo,
                ['status' => PurchaseOrder::STATUS_ORDERED],
                ['status' => PurchaseOrder::STATUS_RECEIVED, 'invoice_number' => $validated['invoice_number']],
                $request->user()->id
            );
        });

        return back()->with('success', "Recebimento da NF {$validated['invoice_number']} confirmado e estoque atualizado.");
    }

    public function adjustStock(Request $request, Product $product, InventoryService $inventory): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManagePurchasing(), 403);

        $validated = $request->validate([
            'quantity_delta' => ['required', 'integer', 'not_in:0', 'min:-10000', 'max:100000'],
            'notes' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $product, $validated, $inventory): void {
            $lockedProduct = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();
            $before = (int) $lockedProduct->stock;
            $delta = (int) $validated['quantity_delta'];
            $after = $before + $delta;

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity_delta' => "O ajuste resultaria em estoque negativo ({$after}).",
                ]);
            }

            $lockedProduct->update(['stock' => $after]);

            $inventory->recordMovement(
                product: $lockedProduct,
                type: StockMovement::TYPE_MANUAL_ADJUSTMENT,
                quantityDelta: $delta,
                stockBefore: $before,
                stockAfter: $after,
                userId: $request->user()->id,
                reference: $lockedProduct,
                notes: $validated['notes'],
            );

            AuditLog::record(
                'inventory.adjusted',
                $lockedProduct,
                ['stock' => $before],
                ['stock' => $after, 'notes' => $validated['notes']],
                $request->user()->id
            );
        });

        return back()->with('success', "Inventário de \"{$product->name}\" ajustado com registro auditável.");
    }
}
