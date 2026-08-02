<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeductionBatchPostedType: int implements HasColor, HasLabel
{
    case Normal = 1;
    case Archive = 2;
    case Surplus = 3;
    case Partial = 4;
    case Wrong = 5;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Normal => 'مرحّل',
            self::Archive => 'فائض أرشيف',
            self::Surplus => 'فائض',
            self::Partial => 'جزئي',
            self::Wrong => 'بالخطأ',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Normal => 'success',
            self::Archive => 'danger',
            self::Partial => 'warning',
            self::Surplus => 'primary',
            self::Wrong => 'gray',
        };
    }
}
