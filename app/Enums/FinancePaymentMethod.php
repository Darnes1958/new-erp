<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FinancePaymentMethod: int implements HasLabel
{
    case Bank = 0;
    case Cash = 1;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Bank => 'مصرفي',
            self::Cash => 'نقداً',
        };
    }
}
