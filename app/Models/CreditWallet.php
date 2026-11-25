<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditWallet extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'balance',
        'reserved_balance',
        'total_acquired',
        'total_spent',
        'daily_limit',
        'is_active',
        'low_balance_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'reserved_balance' => 'decimal:2',
            'total_acquired' => 'decimal:2',
            'total_spent' => 'decimal:2',
            'daily_limit' => 'decimal:2',
            'is_active' => 'boolean',
            'low_balance_notified_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }
}
