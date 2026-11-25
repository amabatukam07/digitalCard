<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Commercial = 'commercial';
    case Purchasing = 'purchasing';
    case Finance = 'finance';
    case Logistics = 'logistics';
    case Auditor = 'auditor';
    case CompanyManager = 'company_manager';
    case CompanyRequester = 'company_requester';

    public function isBackoffice(): bool
    {
        return in_array($this, [
            self::Admin,
            self::Commercial,
            self::Purchasing,
            self::Finance,
            self::Logistics,
            self::Auditor,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador Geral',
            self::Commercial => 'Equipe Comercial',
            self::Purchasing => 'Compras & Estoque',
            self::Finance => 'Financeiro / Tesouraria',
            self::Logistics => 'Operação / Logística',
            self::Auditor => 'Auditor (Somente Leitura)',
            self::CompanyManager => 'Gestor da Empresa Cliente',
            self::CompanyRequester => 'Solicitante da Empresa Cliente',
        };
    }
}
