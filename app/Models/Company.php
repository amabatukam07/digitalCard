<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'cnpj',
        'legal_name',
        'trade_name',
        'state_registration',
        'email',
        'phone',
        'billing_address',
        'billing_cep',
        'status',
        'require_order_approval',
        'low_balance_threshold',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'require_order_approval' => 'boolean',
            'low_balance_threshold' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Company $company): void {
            $company->wallet()->firstOrCreate([], [
                'balance' => 0.00,
                'reserved_balance' => 0.00,
                'total_acquired' => 0.00,
                'total_spent' => 0.00,
                'is_active' => true,
            ]);
        });
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(CreditWallet::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_users')
            ->withPivot(['company_role', 'spending_limit', 'department', 'is_active'])
            ->withTimestamps();
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function activeContract(): ?Contract
    {
        return $this->contracts()
            ->with('plan')
            ->where('status', Contract::STATUS_ACTIVE)
            ->latest('starts_at')
            ->first();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
