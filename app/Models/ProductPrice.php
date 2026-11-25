<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    protected $fillable = [
        'product_id',
        'cost_price',
        'inbound_freight',
        'other_costs',
        'markup_percent',
        'das_rate',
        'sale_price',
        'changed_by',
        'reason',
        'effective_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'inbound_freight' => 'decimal:2',
            'other_costs' => 'decimal:2',
            'markup_percent' => 'decimal:2',
            'das_rate' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'effective_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
