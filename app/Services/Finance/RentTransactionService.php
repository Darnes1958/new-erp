<?php

namespace App\Services\Finance;

use App\Enums\RentTransactionType;
use App\Models\RentProfile;
use App\Models\RentTransaction;
use App\Services\SystemOperationLogger;
use App\Support\SystemOperationType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RentTransactionService
{
    public function __construct(
        protected FinanceBalanceService $balances,
    ) {}

    public function postMonthlyRents(string $periodMonth, string $transactionDate, ?int $rentProfileId = null): bool
    {
        $existsQuery = RentTransaction::query()->where('period_month', $periodMonth);

        if ($rentProfileId) {
            $existsQuery->where('rent_profile_id', $rentProfileId);
        }

        if ($existsQuery->exists()) {
            return false;
        }

        DB::transaction(function () use ($periodMonth, $transactionDate, $rentProfileId): void {
            RentProfile::query()
                ->where('is_active', true)
                ->when($rentProfileId, fn ($query) => $query->whereKey($rentProfileId))
                ->each(function (RentProfile $profile) use ($periodMonth, $transactionDate): void {
                    RentTransaction::query()->create([
                        'rent_profile_id' => $profile->id,
                        'transaction_date' => $transactionDate,
                        'transaction_type' => RentTransactionType::Rent->value,
                        'amount' => $profile->rent_amount,
                        'period_month' => $periodMonth,
                        'notes' => 'إيجار شهر '.$periodMonth,
                        'created_by' => Auth::id(),
                    ]);
                });

            $this->balances->recalculateRentProfiles();
        });

        return true;
    }

    public function recordWithdrawal(array $data): RentTransaction
    {
        return DB::transaction(function () use ($data): RentTransaction {
            $transaction = RentTransaction::query()->create([
                'rent_profile_id' => $data['rent_profile_id'],
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'transaction_type' => RentTransactionType::Withdrawal->value,
                'amount' => $data['amount'],
                'period_month' => '0',
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->balances->recalculateRentProfiles((int) $transaction->rent_profile_id);

            return $transaction;
        });
    }

    public function cancelMonthlyRents(string $periodMonth, ?int $rentProfileId = null): int
    {
        return DB::transaction(function () use ($periodMonth, $rentProfileId): int {
            $query = RentTransaction::query()
                ->where('transaction_type', RentTransactionType::Rent->value)
                ->where('period_month', $periodMonth);

            if ($rentProfileId) {
                $query->where('rent_profile_id', $rentProfileId);
            }

            $transactions = $query->get(['id', 'rent_profile_id']);
            $profileIds = $transactions->pluck('rent_profile_id')->unique()->all();
            $ids = $transactions->pluck('id')->all();

            $deleted = 0;
            if ($ids !== []) {
                $deleted = RentTransaction::query()->whereIn('id', $ids)->delete();
            }

            foreach ($transactions as $transaction) {
                SystemOperationLogger::cancelled(SystemOperationType::RENT_TRANSACTION, $transaction->id);
            }

            if ($profileIds === []) {
                $this->balances->recalculateRentProfiles();
            } else {
                foreach ($profileIds as $profileId) {
                    $this->balances->recalculateRentProfiles((int) $profileId);
                }
            }

            return (int) $deleted;
        });
    }

    public function updateTransaction(RentTransaction $transaction, array $data): RentTransaction
    {
        return DB::transaction(function () use ($transaction, $data): RentTransaction {
            $payload = [
                'transaction_date' => $data['transaction_date'] ?? $transaction->transaction_date,
                'amount' => $data['amount'] ?? $transaction->amount,
                'notes' => $data['notes'] ?? $transaction->notes,
            ];

            if ($transaction->transaction_type === RentTransactionType::Withdrawal) {
                $payload['bank_account_id'] = $data['bank_account_id'] ?? null;
                $payload['cash_box_id'] = $data['cash_box_id'] ?? null;
            }

            if (array_key_exists('period_month', $data) && $transaction->transaction_type === RentTransactionType::Rent) {
                $payload['period_month'] = $data['period_month'];
            }

            $transaction->update($payload);

            SystemOperationLogger::updated(SystemOperationType::RENT_TRANSACTION, $transaction->id);
            $this->balances->recalculateRentProfiles((int) $transaction->rent_profile_id);

            return $transaction->refresh();
        });
    }

    public function deleteTransaction(RentTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $profileId = (int) $transaction->rent_profile_id;
            $id = (int) $transaction->id;

            $transaction->delete();

            SystemOperationLogger::cancelled(SystemOperationType::RENT_TRANSACTION, $id);
            $this->balances->recalculateRentProfiles($profileId);
        });
    }
}
