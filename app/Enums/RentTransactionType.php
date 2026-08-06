<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RentTransactionType: string implements HasLabel
{
    case Rent = 'إيجار';
    case Withdrawal = 'سحب';

    public function getLabel(): ?string
    {
        return $this->value;
    }
}
