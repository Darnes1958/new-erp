<?php

namespace App\Services\Finance;

use App\Enums\SalaryTransactionType;
use App\Models\SalaryProfile;
use App\Models\SalaryTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalaryTransactionService
{
    public function __construct(
        protected FinanceBalanceService $balances,
    ) {}

    public function postMonthlySalaries(string $periodMonth, string $transactionDate): bool
    {
        if (SalaryTransaction::query()->where('period_month', $periodMonth)->exists()) {
            return false;
        }

        DB::transaction(function () use ($periodMonth, $transactionDate): void {
            SalaryProfile::query()
                ->where('is_active', true)
                ->each(function (SalaryProfile $profile) use ($periodMonth, $transactionDate): void {
                    SalaryTransaction::query()->create([
                        'salary_profile_id' => $profile->id,
                        'transaction_date' => $transactionDate,
                        'transaction_type' => SalaryTransactionType::Salary->value,
                        'amount' => $profile->salary_amount,
                        'period_month' => $periodMonth,
                        'notes' => 'مرتب شهر '.$periodMonth,
                        'created_by' => Auth::id(),
                    ]);
                });

            $this->balances->recalculateSalaryProfiles();
        });

        return true;
    }

    public function recordTransaction(array $data, SalaryTransactionType $type): SalaryTransaction
    {
        return DB::transaction(function () use ($data, $type): SalaryTransaction {
            $transaction = SalaryTransaction::query()->create([
                'salary_profile_id' => $data['salary_profile_id'],
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'transaction_type' => $type->value,
                'amount' => $data['amount'],
                'period_month' => $data['period_month'] ?? '0',
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->balances->recalculateSalaryProfiles((int) $transaction->salary_profile_id);

            return $transaction;
        });
    }

    public function cancelMonthlySalaries(string $periodMonth): void
    {
        DB::transaction(function () use ($periodMonth): void {
            SalaryTransaction::query()->where('period_month', $periodMonth)->delete();
            $this->balances->recalculateSalaryProfiles();
        });
    }
}
