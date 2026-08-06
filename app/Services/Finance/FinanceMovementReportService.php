<?php

namespace App\Services\Finance;

use App\Models\Expense;
use App\Models\RentProfile;
use App\Models\RentTransaction;
use App\Models\SalaryProfile;
use App\Models\SalaryTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class FinanceMovementReportService
{
    public function expensesQuery(?int $expenseTypeId, ?string $dateFrom, ?string $dateTo): Builder
    {
        return Expense::query()
            ->with(['bankAccount', 'cashBox'])
            ->when(filled($expenseTypeId), fn (Builder $query): Builder => $query->where('expense_type_id', $expenseTypeId))
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('expense_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('expense_date', '<=', $dateTo));
    }

    public function salaryTransactionsQuery(?int $salaryProfileId): Builder
    {
        return SalaryTransaction::query()
            ->with(['bankAccount', 'cashBox'])
            ->when(filled($salaryProfileId), fn (Builder $query): Builder => $query->where('salary_profile_id', $salaryProfileId));
    }

    public function rentTransactionsQuery(?int $rentProfileId): Builder
    {
        return RentTransaction::query()
            ->with(['bankAccount', 'cashBox'])
            ->when(filled($rentProfileId), fn (Builder $query): Builder => $query->where('rent_profile_id', $rentProfileId));
    }

    public function paymentSourceLabel(Model $record): string
    {
        if ($record->bank_account_id) {
            return (string) ($record->bankAccount?->name ?? '—');
        }

        if ($record->cash_box_id) {
            return (string) ($record->cashBox?->name ?? '—');
        }

        return '—';
    }

    public function expensePaymentSourceLabel(Expense $expense): string
    {
        return $this->paymentSourceLabel($expense);
    }

    /**
     * @param  Collection<int, Expense|SalaryTransaction|RentTransaction>  $rows
     */
    public function totalAmount(Collection $rows): float
    {
        return round((float) $rows->sum(fn (Model $row): float => (float) $row->amount), 3);
    }

    public function resolveSalaryProfile(?int $salaryProfileId): ?SalaryProfile
    {
        if (! filled($salaryProfileId)) {
            return null;
        }

        return SalaryProfile::query()->find($salaryProfileId);
    }

    public function resolveRentProfile(?int $rentProfileId): ?RentProfile
    {
        if (! filled($rentProfileId)) {
            return null;
        }

        return RentProfile::query()->find($rentProfileId);
    }

    public function formatPeriodMonth(?string $periodMonth): string
    {
        if (! filled($periodMonth) || $periodMonth === '0') {
            return '—';
        }

        return $periodMonth;
    }
}
