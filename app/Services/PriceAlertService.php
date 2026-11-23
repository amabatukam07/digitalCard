<?php

namespace App\Services;

use App\Models\PriceAlert;
use App\Models\Product;
use App\Notifications\ProductPriceDropNotification;

class PriceAlertService
{
    public function dispatchTriggeredAlerts(Product $product): int
    {
        $currentPrice = round((float) $product->price, 2);

        $alerts = PriceAlert::query()
            ->with('user')
            ->where('product_id', $product->id)
            ->where('target_price', '>=', $currentPrice)
            ->where(function ($query) use ($currentPrice): void {
                $query->whereNull('notified_at')
                    ->orWhere('last_notified_price', '>', $currentPrice);
            })
            ->get();

        $dispatched = 0;

        foreach ($alerts as $alert) {
            if (! $alert->user) {
                continue;
            }

            $alert->user->notify(new ProductPriceDropNotification(
                product: $product,
                targetPrice: (float) $alert->target_price,
                currentPrice: $currentPrice,
            ));

            $alert->forceFill([
                'notified_at' => now(),
                'last_notified_price' => $currentPrice,
            ])->save();

            $dispatched++;
        }

        return $dispatched;
    }
}
