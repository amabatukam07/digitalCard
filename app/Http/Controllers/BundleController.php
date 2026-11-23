<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\ProductBundle;
use App\Services\CartPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BundleController extends Controller
{
    public function index(): View
    {
        $bundles = ProductBundle::query()
            ->with(['items.product.category'])
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        return view('catalog.bundles', compact('bundles'));
    }

    public function addToCart(Request $request, ProductBundle $bundle, CartPricing $pricing): RedirectResponse
    {
        abort_unless($bundle->is_active, 404);

        $bundle->load(['items.product']);

        $cart = $request->session()->get('cart', []);
        $addedCount = 0;
        $skippedNames = [];

        foreach ($bundle->items as $bundleItem) {
            $product = $bundleItem->product;
            if (! $product || ! $product->is_active || $product->stock < 1) {
                if ($product) {
                    $skippedNames[] = $product->name;
                }

                continue;
            }

            $qtyToAdd = max(1, (int) $bundleItem->quantity);
            $currentQty = (int) ($cart[$product->id]['quantity'] ?? 0);
            $targetQty = min($product->stock, $currentQty + $qtyToAdd);

            $cart[$product->id] = [
                'product_id' => $product->id,
                'quantity' => $targetQty,
                'price_snapshot' => (float) $product->price,
            ];

            if ($request->user()) {
                CartItem::updateOrCreate(
                    ['user_id' => $request->user()->id, 'product_id' => $product->id],
                    ['quantity' => $targetQty, 'price_snapshot' => (float) $product->price]
                );
            }

            $addedCount++;
        }

        if ($addedCount === 0) {
            return back()->with('error', 'Nenhum item deste kit está disponível em estoque no momento.');
        }

        $request->session()->put('cart', $cart);

        $couponAppliedMsg = '';
        if (! $request->session()->has('cart_coupon') && ! empty($bundle->recommended_coupon_code)) {
            $coupon = Coupon::whereRaw('UPPER(code) = ?', [strtoupper($bundle->recommended_coupon_code)])->first();
            if ($coupon) {
                $summary = $pricing->calculate($cart, null, $request->user(), null, null, false);
                if ($coupon->isValidForUser($request->user(), $summary['subtotal_cents'])) {
                    $request->session()->put('cart_coupon', $coupon->code);
                    $couponAppliedMsg = " Cupom {$coupon->code} aplicado automaticamente!";
                }
            }
        }

        $warningSuffix = $skippedNames !== []
            ? ' (Indisponíveis ignorados: '.implode(', ', $skippedNames).')'
            : '';

        return redirect()->route('cart.index')->with(
            'success',
            "Kit \"{$bundle->name}\" adicionado ao carrinho ({$addedCount} itens).{$couponAppliedMsg}{$warningSuffix}"
        );
    }
}
