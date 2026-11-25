<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'price',
        'credits_amount',
        'is_custom_amount',
        'min_amount',
        'target_profile',
        'description',
        'benefits',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'credits_amount' => 'decimal:2',
            'is_custom_amount' => 'boolean',
            'min_amount' => 'decimal:2',
            'benefits' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
