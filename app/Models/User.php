<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * is_admin e role permanecem fora de $fillable para evitar escalação de privilégio via Mass Assignment.
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_users')
            ->withPivot(['company_role', 'spending_limit', 'department', 'is_active'])
            ->withTimestamps();
    }

    public function companyMemberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function primaryCompany(): ?Company
    {
        $membership = $this->companyMemberships()
            ->with('company.wallet')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        return $membership?->company;
    }

    public function companyMembership(?Company $company = null): ?CompanyUser
    {
        $query = $this->companyMemberships()->where('is_active', true);

        if ($company !== null) {
            $query->where('company_id', $company->id);
        }

        return $query->orderBy('id')->first();
    }

    public function belongsToCompany(int $companyId): bool
    {
        return $this->companyMemberships()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->exists();
    }

    public function isCompanyManager(?Company $company = null): bool
    {
        $membership = $this->companyMembership($company);

        return $membership !== null && $membership->isManager();
    }

    public function activeCreditWallet(): ?CreditWallet
    {
        return $this->primaryCompany()?->wallet;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function productReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function priceAlerts(): HasMany
    {
        return $this->hasMany(PriceAlert::class);
    }

    public function productQuestions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    public function roleEnum(): UserRole
    {
        $role = UserRole::tryFrom((string) ($this->role ?? ''));

        if ($this->is_admin && ($role === null || ! $role->isBackoffice())) {
            return UserRole::Admin;
        }

        return $role ?? UserRole::CompanyRequester;
    }

    public function hasAdminAccess(): bool
    {
        return (bool) $this->is_admin || $this->roleEnum()->isBackoffice();
    }

    public function canManageCompanies(): bool
    {
        return (bool) $this->is_admin || in_array($this->roleEnum(), [UserRole::Admin, UserRole::Commercial], true);
    }

    public function canManagePurchasing(): bool
    {
        return (bool) $this->is_admin || in_array($this->roleEnum(), [UserRole::Admin, UserRole::Purchasing], true);
    }

    public function canManageCatalog(): bool
    {
        return (bool) $this->is_admin || in_array($this->roleEnum(), [UserRole::Admin, UserRole::Purchasing, UserRole::Commercial], true);
    }

    public function canManageFinance(): bool
    {
        return (bool) $this->is_admin || in_array($this->roleEnum(), [UserRole::Admin, UserRole::Finance], true);
    }

    public function canManageOrders(): bool
    {
        return (bool) $this->is_admin || in_array($this->roleEnum(), [UserRole::Admin, UserRole::Logistics], true);
    }

    public function canManageLogistics(): bool
    {
        return $this->canManageOrders();
    }
}
