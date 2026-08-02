<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeductionBatchStatus: int implements HasColor, HasLabel
{
    case Open = 0;
    case Posted = 1;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Open => 'غير مرحّلة',
            self::Posted => 'مرحّلة',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Open => 'warning',
            self::Posted => 'success',
        };
    }
}
