<?php

namespace App\Support;

use App\Enums\BankCommissionType;
use App\Models\BankMain;

class BankCommissionCalculator
{
    public static function calculate(
        ?BankMain $bankMain,
        float $collectedTotal,
        int $installmentsCount,
    ): float {
        if (! $bankMain) {
            return 0.0;
        }

        $ratio = (float) $bankMain->ratio;

        return round(match ($bankMain->r_type) {
            BankCommissionType::Amount => $ratio * $installmentsCount,
            BankCommissionType::Percentage => $collectedTotal * ($ratio / 100),
        }, 3);
    }
}
