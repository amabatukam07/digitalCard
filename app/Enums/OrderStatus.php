<?php

namespace App\Enums;

enum OrderStatus: string
{
    case AwaitingApproval = 'Aguardando aprovação';
    case Requested = 'Produto solicitado';
    case Separating = 'Em separação';
    case Shipped = 'Enviado';
    case Delivered = 'Produto entregue';
    case Cancelled = 'Pedido cancelado';
    case Returned = 'Devolvido';

    /**
     * Define as transições permitidas da máquina de estados de pedidos B2B.
     */
    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return match ($this) {
            self::AwaitingApproval => in_array($target, [
                self::Requested,
                self::Cancelled,
            ], true),
            self::Requested => in_array($target, [
                self::Separating,
                self::Shipped,
                self::Delivered,
                self::Cancelled,
            ], true),
            self::Separating => in_array($target, [
                self::Shipped,
                self::Delivered,
                self::Cancelled,
            ], true),
            self::Shipped => in_array($target, [
                self::Delivered,
            ], true),
            self::Delivered => in_array($target, [
                self::Returned,
            ], true),
            self::Cancelled, self::Returned => false,
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [
            self::AwaitingApproval,
            self::Requested,
            self::Separating,
            self::Shipped,
        ], true);
    }

    public function label(): string
    {
        return $this->value;
    }
}
