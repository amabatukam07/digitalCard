<?php

namespace App\Models;

use App\Services\CartPricing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductBundle extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'subtitle',
        'description',
        'badge',
        'recommended_coupon_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_id');
    }

    public function totalPrice(): float
    {
        $totalCents = 0;
        foreach ($this->items as $item) {
            if (! $item->product) {
                continue;
            }

            $unitCents = CartPricing::toCents((float) $item->product->price);
            $totalCents += $unitCents * max(1, (int) $item->quantity);
        }

        return CartPricing::toDecimal($totalCents);
    }

    public function isAvailable(): bool
    {
        if (! $this->is_active || $this->items->isEmpty()) {
            return false;
        }

        foreach ($this->items as $item) {
            if (! $item->product || ! $item->product->is_active || $item->product->stock < $item->quantity) {
                return false;
            }
        }

        return true;
    }
}
