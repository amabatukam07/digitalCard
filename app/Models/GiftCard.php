<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GiftCard extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REDEEMED = 'redeemed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'code',
        'purchaser_user_id',
        'redeemer_user_id',
        'initial_amount',
        'remaining_amount',
        'recipient_name',
        'recipient_email',
        'message',
        'status',
        'redeemed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'initial_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'redeemed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_user_id');
    }

    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemer_user_id');
    }

    public function isRedeemable(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE || (float) $this->remaining_amount <= 0) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public static function generateUniqueCode(): string
    {
        do {
            $chunkA = strtoupper(Str::random(4));
            $chunkB = strtoupper(Str::random(4));
            $chunkC = strtoupper(Str::random(4));
            $code = "DCGC-{$chunkA}-{$chunkB}-{$chunkC}";
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
