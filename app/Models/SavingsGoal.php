<?php

namespace App\Models;

use App\Services\CartPricing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsGoal extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'name',
        'target_amount',
        'current_amount',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'current_amount' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function progressPercent(): int
    {
        $targetCents = CartPricing::toCents((float) $this->target_amount);
        if ($targetCents <= 0) {
            return 100;
        }

        $currentCents = CartPricing::toCents((float) $this->current_amount);

        return (int) min(100, max(0, round(($currentCents / $targetCents) * 100)));
    }

    public function remainingAmount(): float
    {
        $diffCents = max(
            0,
            CartPricing::toCents((float) $this->target_amount) - CartPricing::toCents((float) $this->current_amount)
        );

        return CartPricing::toDecimal($diffCents);
    }
}
