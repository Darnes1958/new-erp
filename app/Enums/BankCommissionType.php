<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BankCommissionType: int implements HasColor, HasLabel
{
    case Amount = 1;
    case Percentage = 2;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Amount => 'قيمة',
            self::Percentage => 'نسبة',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Amount => 'info',
            self::Percentage => 'primary',
        };
    }

    public function ratioLabel(): string
    {
        return match ($this) {
            self::Amount => 'المبلغ',
            self::Percentage => 'النسبة',
        };
    }
}
