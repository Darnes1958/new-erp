<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SalaryTransactionType: string implements HasLabel
{
    case Salary = 'مرتب';
    case Withdrawal = 'سحب';
    case Addition = 'اضافة';
    case Deduction = 'خصم';

    public function getLabel(): ?string
    {
        return $this->value;
    }
}
