<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Admin = 'admin';
    case Finance = 'finance';
    case Logistics = 'logistics';
    case Catalog = 'catalog';
    case Auditor = 'auditor';

    public function isBackoffice(): bool
    {
        return $this !== self::Customer;
    }

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Cliente',
            self::Admin => 'Administrador Geral',
            self::Finance => 'Financeiro / Tesouraria',
            self::Logistics => 'Logística / Pedidos',
            self::Catalog => 'Catálogo & Cupons',
            self::Auditor => 'Auditor (Somente Leitura)',
        };
    }
}
