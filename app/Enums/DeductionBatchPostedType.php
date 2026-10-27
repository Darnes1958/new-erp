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
    case Cancelled = 6;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Normal => 'مرحلة',
            self::Archive => 'أرشيف',
            self::Surplus => 'فائض',
            self::Partial => 'جزئي',
            self::Wrong => 'بالخطأ',
            self::Cancelled => 'ملغية',
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
            self::Cancelled => 'warning',
        };
    }

    public function viewerColor(): string
    {
        return match ($this) {
            self::Normal => '#16a34a',
            self::Archive => '#dc2626',
            self::Surplus => '#2563eb',
            self::Partial => '#d97706',
            self::Wrong => '#6b7280',
            self::Cancelled => '#b45309',
        };
    }
}
