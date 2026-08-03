<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InstallmentRecordStatus: int implements HasColor, HasLabel
{
    case Legacy = 0;
    case Open = 1;
    case Returned = 2;
    case Corrected = 3;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Legacy, self::Open => 'غير مرجع',
            self::Returned => 'مرجع',
            self::Corrected => 'مصحح',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Legacy, self::Open => 'info',
            self::Returned => 'success',
            self::Corrected => 'primary',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open || $this === self::Legacy;
    }
}
