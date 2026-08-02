<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InstallmentDeductionType: int implements HasColor, HasLabel
{
    case Cash = 1;
    case Bank = 2;
    case Cheque = 3;
    case Electronic = 4;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Cash => 'نقداً',
            self::Bank => 'المصرف',
            self::Cheque => 'صك',
            self::Electronic => 'إلكتروني',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Cash => 'info',
            self::Bank => 'primary',
            self::Cheque => 'success',
            self::Electronic => 'warning',
        };
    }
}
