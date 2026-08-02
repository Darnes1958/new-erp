<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeductionBatchEntryType: int implements HasColor, HasLabel
{
    case Active = 1;
    case Archive = 2;
    case Wrong = 5;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Active => 'قائم',
            self::Archive => 'أرشيف',
            self::Wrong => 'بالخطأ',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'info',
            self::Archive => 'danger',
            self::Wrong => 'warning',
        };
    }
}
