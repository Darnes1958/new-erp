<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InstallmentReturnType: int implements HasColor, HasLabel
{
    case FromSurplus = 1;
    case FromWrong = 2;
    case FromDeduction = 3;
    case AmountReturn = 4;
    case FromCancelled = 5;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FromSurplus => 'من الفائض',
            self::FromWrong => 'من الخطأ',
            self::FromDeduction => 'من قسط مخصوم',
            self::AmountReturn => 'ترجيع مبلغ',
            self::FromCancelled => 'من ملغي',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::FromSurplus => 'success',
            self::FromWrong => 'info',
            self::FromDeduction => 'primary',
            self::AmountReturn => 'warning',
            self::FromCancelled => 'danger',
        };
    }
}
