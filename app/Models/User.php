<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * is_admin removido de $fillable para impedir escalação de privilégio via Mass Assignment.
     */
    protected $fillable = [
        'name',
        'email',
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

    protected static function booted(): void
    {
        static::created(function (User $user) {
            $user->virtualCard()->create([
                'card_number' => VirtualCard::generateUniqueCardNumber(),
                'cvv' => VirtualCard::generateCvv(),
                'expiration_date' => VirtualCard::DEFAULT_EXPIRATION_DATE,
                'balance' => 0.00,
                'cashback_earned' => 0.00,
            ]);
        });
    }

    public function virtualCard(): HasOne
    {
        return $this->hasOne(VirtualCard::class);
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

    public function sentGiftCards(): HasMany
    {
        return $this->hasMany(GiftCard::class, 'purchaser_user_id');
    }

    public function redeemedGiftCards(): HasMany
    {
        return $this->hasMany(GiftCard::class, 'redeemer_user_id');
    }

    public function productQuestions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    public function savingsGoals(): HasMany
    {
        return $this->hasMany(SavingsGoal::class);
    }

    public function roleEnum(): UserRole
    {
        $role = UserRole::tryFrom((string) ($this->role ?? ''));

        if ($this->is_admin && ($role === null || $role === UserRole::Customer)) {
            return UserRole::Admin;
        }

        return $role ?? UserRole::Customer;
    }

    public function hasAdminAccess(): bool
    {
        return (bool) $this->is_admin || $this->roleEnum()->isBackoffice();
    }

    public function canApproveRecharges(): bool
    {
        return (bool) $this->is_admin || in_array($this->roleEnum(), [UserRole::Admin, UserRole::Finance], true);
    }

    public function canManageOrders(): bool
    {
        return (bool) $this->is_admin || in_array($this->roleEnum(), [UserRole::Admin, UserRole::Logistics], true);
    }

    public function canManageCatalog(): bool
    {
        return (bool) $this->is_admin || in_array($this->roleEnum(), [UserRole::Admin, UserRole::Catalog], true);
    }
}
