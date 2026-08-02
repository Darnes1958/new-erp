<?php

namespace App\Filament\Ins\Concerns;

use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

trait RecalculatesInstallmentAmount
{
    public static function syncInstallmentAmount(Get $get, Set $set, mixed $count = null): void
    {
        $total = (float) $get('contract_total');
        $installmentCount = $count !== null && $count !== ''
            ? (int) $count
            : (int) $get('installment_count');

        if ($total > 0 && $installmentCount > 0) {
            $set('installment_amount', round($total / $installmentCount, 3));
        }
    }
}
