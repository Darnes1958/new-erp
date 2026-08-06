<?php

namespace App\Services\Finance;

use App\Enums\RentTransactionType;
use App\Models\RentProfile;
use App\Models\RentTransaction;
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
}
