<?php

namespace App\Enums;

enum LoyaltyTier: string
{
    case Bronze = 'bronze';
    case Silver = 'prata';
    case Gold = 'ouro';
    case Black = 'black';

    public function label(): string
    {
        return match ($this) {
            self::Bronze => 'Bronze',
            self::Silver => 'Prata',
            self::Gold => 'Ouro',
            self::Black => 'Black',
        };
    }

    public function minDeliveredSpendCents(): int
    {
        return match ($this) {
            self::Bronze => 0,
            self::Silver => 250000,
            self::Gold => 800000,
            self::Black => 2000000,
        };
    }

    public function bonusCashbackPercent(): float
    {
        return match ($this) {
            self::Bronze => 0.0,
            self::Silver => 1.0,
            self::Gold => 2.0,
            self::Black => 3.5,
        };
    }

    public function freeShippingMinCents(): int
    {
        return match ($this) {
            self::Bronze => 29900,
            self::Silver => 19900,
            self::Gold => 9900,
            self::Black => 0,
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Bronze => 'bg-stone-100 text-stone-700 border-stone-200 dark:bg-stone-800 dark:text-stone-300 dark:border-stone-700',
            self::Silver => 'bg-slate-100 text-slate-800 border-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-600',
            self::Gold => 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
            self::Black => 'bg-neutral-900 text-amber-300 border-neutral-700 dark:bg-neutral-950 dark:text-amber-300 dark:border-amber-500/40',
        };
    }

    public function nextTier(): ?self
    {
        return match ($this) {
            self::Bronze => self::Silver,
            self::Silver => self::Gold,
            self::Gold => self::Black,
            self::Black => null,
        };
    }

    public static function fromDeliveredSpendCents(int $cents): self
    {
        return match (true) {
            $cents >= self::Black->minDeliveredSpendCents() => self::Black,
            $cents >= self::Gold->minDeliveredSpendCents() => self::Gold,
            $cents >= self::Silver->minDeliveredSpendCents() => self::Silver,
            default => self::Bronze,
        };
    }
}
