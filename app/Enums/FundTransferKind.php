<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FundTransferKind: int implements HasColor, HasLabel
{
    case CashToCash = 1;
    case CashToBank = 2;
    case BankToCash = 3;
    case BankToBank = 4;

    public function getLabel(): string
    {
        return match ($this) {
            self::CashToCash => 'خزينة إلى خزينة',
            self::CashToBank => 'خزينة إلى مصرف',
            self::BankToCash => 'مصرف إلى خزينة',
            self::BankToBank => 'مصرف إلى مصرف',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::CashToCash, self::CashToBank => 'success',
            self::BankToCash, self::BankToBank => 'danger',
        };
    }

    public function usesFromCashBox(): bool
    {
        return in_array($this, [self::CashToCash, self::CashToBank], true);
    }

    public function usesToCashBox(): bool
    {
        return in_array($this, [self::CashToCash, self::BankToCash], true);
    }

    public function usesFromBankAccount(): bool
    {
        return in_array($this, [self::BankToCash, self::BankToBank], true);
    }

    public function usesToBankAccount(): bool
    {
        return in_array($this, [self::CashToBank, self::BankToBank], true);
    }

    public function paymentMethodId(): int
    {
        return $this === self::BankToBank ? 2 : 1;
    }
}
