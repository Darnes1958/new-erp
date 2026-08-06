<?php

namespace App\Services\Finance;

use App\Enums\RentTransactionType;
use App\Enums\SalaryTransactionType;
use App\Models\RentProfile;
use App\Models\SalaryProfile;

class FinanceBalanceService
{
    public function recalculateSalaryProfiles(?int $profileId = null): void
    {
        $query = SalaryProfile::query();

        if ($profileId !== null) {
            $query->whereKey($profileId);
        }

        $query->each(function (SalaryProfile $profile): void {
            $transactions = $profile->transactions();

            $withdrawals = (float) $transactions->clone()
                ->where('transaction_type', SalaryTransactionType::Withdrawal->value)
                ->sum('amount');
            $deductions = (float) $transactions->clone()
                ->where('transaction_type', SalaryTransactionType::Deduction->value)
                ->sum('amount');
            $salaries = (float) $transactions->clone()
                ->where('transaction_type', SalaryTransactionType::Salary->value)
                ->sum('amount');
            $additions = (float) $transactions->clone()
                ->where('transaction_type', SalaryTransactionType::Addition->value)
                ->sum('amount');

            $profile->forceFill([
                'balance' => round($withdrawals + $deductions - $salaries - $additions, 3),
            ])->saveQuietly();
        });
    }

    public function recalculateRentProfiles(?int $profileId = null): void
    {
        $query = RentProfile::query();

        if ($profileId !== null) {
            $query->whereKey($profileId);
        }

        $query->each(function (RentProfile $profile): void {
            $transactions = $profile->transactions();

            $withdrawals = (float) $transactions->clone()
                ->where('transaction_type', RentTransactionType::Withdrawal->value)
                ->sum('amount');
            $rents = (float) $transactions->clone()
                ->where('transaction_type', RentTransactionType::Rent->value)
                ->sum('amount');

            $profile->forceFill([
                'balance' => round($withdrawals - $rents, 3),
            ])->saveQuietly();
        });
    }
}
