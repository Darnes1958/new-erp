<?php

namespace App\Services\Finance;

use App\Enums\SalaryTransactionType;
use App\Models\SalaryProfile;
use App\Models\SalaryTransaction;
use App\Services\SystemOperationLogger;
use App\Support\SystemOperationType;
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

    public function cancelMonthlySalaries(string $periodMonth): int
    {
        return DB::transaction(function () use ($periodMonth): int {
            $transactions = SalaryTransaction::query()
                ->where('transaction_type', SalaryTransactionType::Salary->value)
                ->where('period_month', $periodMonth)
                ->get(['id', 'salary_profile_id']);

            $ids = $transactions->pluck('id')->all();
            $profileIds = $transactions->pluck('salary_profile_id')->unique()->all();

            if ($ids === []) {
                return 0;
            }

            SalaryTransaction::query()->whereIn('id', $ids)->delete();

            foreach ($ids as $id) {
                SystemOperationLogger::cancelled(SystemOperationType::SALARY, $id);
            }

            foreach ($profileIds as $profileId) {
                $this->balances->recalculateSalaryProfiles((int) $profileId);
            }

            return count($ids);
        });
    }

    public function updateTransaction(SalaryTransaction $transaction, array $data): SalaryTransaction
    {
        return DB::transaction(function () use ($transaction, $data): SalaryTransaction {
            $payload = [
                'transaction_date' => $data['transaction_date'] ?? $transaction->transaction_date,
                'amount' => $data['amount'] ?? $transaction->amount,
                'notes' => $data['notes'] ?? $transaction->notes,
            ];

            if ($transaction->transaction_type === SalaryTransactionType::Salary && array_key_exists('period_month', $data)) {
                $payload['period_month'] = $data['period_month'];
            }

            if ($transaction->transaction_type === SalaryTransactionType::Withdrawal) {
                $payload['bank_account_id'] = $data['bank_account_id'] ?? null;
                $payload['cash_box_id'] = $data['cash_box_id'] ?? null;
            }

            $transaction->update($payload);

            SystemOperationLogger::updated(SystemOperationType::SALARY, $transaction->id);
            $this->balances->recalculateSalaryProfiles((int) $transaction->salary_profile_id);

            return $transaction->refresh();
        });
    }

    public function deleteTransaction(SalaryTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $profileId = (int) $transaction->salary_profile_id;
            $id = (int) $transaction->id;

            $transaction->delete();

            SystemOperationLogger::cancelled(SystemOperationType::SALARY, $id);
            $this->balances->recalculateSalaryProfiles($profileId);
        });
    }
}
