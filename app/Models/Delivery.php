<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_RETURNED = 'returned';

    protected $fillable = [
        'order_id',
        'status',
        'carrier',
        'tracking_code',
        'scheduled_route',
        'receiver_name',
        'proof_of_delivery',
        'dispatched_at',
        'delivered_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
