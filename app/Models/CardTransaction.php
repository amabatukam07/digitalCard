<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CardTransaction extends Model
{
    use SoftDeletes;

    public const TYPE_RECHARGE = 'recarga';

    public const TYPE_PURCHASE = 'compra';

    public const TYPE_CASHBACK = 'cashback';

    public const TYPE_REFUND = 'estorno';

    public const TYPE_CASHBACK_REVERSAL = 'estorno_cashback';

    public const TYPE_TRANSFER_OUT = 'transferencia_enviada';

    public const TYPE_TRANSFER_IN = 'transferencia_recebida';

    public const TYPE_GIFT_CARD_PURCHASE = 'gift_card_compra';

    public const TYPE_GIFT_CARD_REDEEM = 'gift_card_resgate';

    public const TYPE_GOAL_DEPOSIT = 'cofrinho_deposito';

    public const TYPE_GOAL_WITHDRAW = 'cofrinho_resgate';

    protected $fillable = [
        'virtual_card_id',
        'type',
        'amount',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function virtualCard(): BelongsTo
    {
        return $this->belongsTo(VirtualCard::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
