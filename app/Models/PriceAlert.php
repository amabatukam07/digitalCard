<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceAlert extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'target_price',
        'notified_at',
        'last_notified_price',
    ];

    protected function casts(): array
    {
        return [
            'target_price' => 'decimal:2',
            'notified_at' => 'datetime',
            'last_notified_price' => 'decimal:2',
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
}
