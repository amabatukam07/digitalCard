<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    public const STATUS_AWAITING_APPROVAL = 'Aguardando aprovação';

    public const STATUS_REQUESTED = 'Produto solicitado';

    public const STATUS_SEPARATING = 'Em separação';

    public const STATUS_SHIPPED = 'Enviado';

    public const STATUS_DELIVERED = 'Produto entregue';

    public const STATUS_CANCELLED = 'Pedido cancelado';

    public const STATUS_RETURNED = 'Devolvido';

    protected $fillable = [
        'company_id',
        'user_id',
        'approved_by_user_id',
        'approved_at',
        'idempotency_key',
        'subtotal_amount',
        'discount_amount',
        'coupon_code',
        'shipping_cost',
        'total_amount',
        'credits_debited',
        'total_cost_snapshot',
        'estimated_das_amount',
        'estimated_margin_amount',
        'status',
        'shipping_address',
        'shipping_cep',
        'tracking_code',
        'department',
        'notes',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'credits_debited' => 'decimal:2',
            'total_cost_snapshot' => 'decimal:2',
            'estimated_das_amount' => 'decimal:2',
            'estimated_margin_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function statusEnum(): OrderStatus
    {
        return OrderStatus::tryFrom((string) $this->status) ?? OrderStatus::Requested;
    }

    public function canRequestRegretReturn(): bool
    {
        if ($this->status !== self::STATUS_DELIVERED || ! $this->delivered_at) {
            return false;
        }

        return now()->lte($this->delivered_at->copy()->addDays(7));
    }
}
