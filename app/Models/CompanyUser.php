<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyUser extends Model
{
    public const ROLE_MANAGER = 'manager';

    public const ROLE_REQUESTER = 'requester';

    protected $fillable = [
        'company_id',
        'user_id',
        'company_role',
        'spending_limit',
        'department',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'spending_limit' => 'decimal:2',
            'is_active' => 'boolean',
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

    public function isManager(): bool
    {
        return $this->company_role === self::ROLE_MANAGER;
    }

    public function roleLabel(): string
    {
        return $this->isManager() ? 'Gestor da empresa' : 'Solicitante';
    }
}
