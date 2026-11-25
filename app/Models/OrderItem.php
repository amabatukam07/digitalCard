<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'unit_presentation',
        'quantity',
        'unit_price',
        'unit_cost_snapshot',
        'das_rate_snapshot',
        'discount_allocated',
        'item_status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'unit_cost_snapshot' => 'decimal:2',
            'das_rate_snapshot' => 'decimal:2',
            'discount_allocated' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->product_name ?: ($this->product?->name ?? 'Item NexoSupri');
    }
}
