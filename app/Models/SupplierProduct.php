<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProduct extends Model
{
    protected $fillable = [
        'supplier_id',
        'product_id',
        'supplier_sku',
        'supplier_cost',
        'lead_time_days',
        'last_quoted_at',
    ];

    protected function casts(): array
    {
        return [
            'supplier_cost' => 'decimal:2',
            'lead_time_days' => 'integer',
            'last_quoted_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
