<?php

namespace App\Http\Controllers;

use App\Models\PriceAlert;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PriceAlertController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $validated = $request->validate([
            'target_price' => ['required', 'numeric', 'min:1.00', 'max:999999.99'],
        ]);

        $targetPrice = round((float) $validated['target_price'], 2);

        PriceAlert::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
            ],
            [
                'target_price' => $targetPrice,
                'notified_at' => null,
                'last_notified_price' => null,
            ]
        );

        $formatted = 'R$ '.number_format($targetPrice, 2, ',', '.');

        return back()->with('success', "Alerta criado! Avisaremos quando {$product->name} chegar a {$formatted} ou menos.");
    }

    public function destroy(Request $request, PriceAlert $priceAlert): RedirectResponse
    {
        abort_unless((int) $priceAlert->user_id === (int) $request->user()->id, 403);

        $priceAlert->delete();

        return back()->with('success', 'Alerta de preço removido.');
    }
}
