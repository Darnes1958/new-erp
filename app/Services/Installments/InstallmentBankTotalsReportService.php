<?php

namespace App\Services\Installments;

use App\Enums\InstallmentRecordStatus;
use App\Models\InstallmentBank;
use App\Models\PayrollBank;
use App\Models\WrongDeduction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InstallmentBankTotalsReportService
{
    public function branchTotalsQuery(): Builder
    {
        return InstallmentBank::query()
            ->whereHas('installmentContracts')
            ->withCount(['installmentContracts as contracts_count'])
            ->withSum(['installmentContracts as contracts_total'], 'contract_total')
            ->withSum(['installmentContracts as total_paid'], 'total_paid')
            ->withSum(['installmentContracts as balance_total'], 'balance')
            ->withSum(['installmentContracts as surplus_total'], 'surplus_amount')
            ->withSum(['installmentContracts as suspended_total'], 'suspended_amount')
            ->selectSub($this->wrongTotalSubquery('installment_banks.payroll_bank_id'), 'wrong_total');
    }

    public function payrollTotalsQuery(): Builder
    {
        return PayrollBank::query()
            ->whereHas('installmentContracts')
            ->withCount(['installmentContracts as contracts_count'])
            ->withSum(['installmentContracts as contracts_total'], 'contract_total')
            ->withSum(['installmentContracts as total_paid'], 'total_paid')
            ->withSum(['installmentContracts as balance_total'], 'balance')
            ->withSum(['installmentContracts as surplus_total'], 'surplus_amount')
            ->withSum(['installmentContracts as suspended_total'], 'suspended_amount')
            ->selectSub($this->wrongTotalSubquery('payroll_banks.id'), 'wrong_total');
    }

    public function reportQuery(int $filterBy): Builder
    {
        return $filterBy === 2
            ? $this->payrollTotalsQuery()
            : $this->branchTotalsQuery();
    }

    public function reportTitle(int $filterBy): string
    {
        return $filterBy === 2
            ? 'إجمالي المصارف - بالتجميعي'
            : 'إجمالي المصارف - بفروع المصارف';
    }

    /**
     * @return array{
     *     contracts_count: int,
     *     contracts_total: float,
     *     total_paid: float,
     *     balance_total: float,
     *     surplus_total: float,
     *     suspended_total: float,
     *     wrong_total: float
     * }
     */
    public function summary(int $filterBy): array
    {
        $rows = $this->reportQuery($filterBy)->get();

        return [
            'contracts_count' => (int) $rows->sum('contracts_count'),
            'contracts_total' => (float) $rows->sum('contracts_total'),
            'total_paid' => (float) $rows->sum('total_paid'),
            'balance_total' => (float) $rows->sum('balance_total'),
            'surplus_total' => (float) $rows->sum('surplus_total'),
            'suspended_total' => (float) $rows->sum('suspended_total'),
            'wrong_total' => (float) $rows->sum('wrong_total'),
        ];
    }

    /**
     * @return Collection<int, InstallmentBank|PayrollBank>
     */
    public function exportRows(int $filterBy): Collection
    {
        return $this->reportQuery($filterBy)->orderBy('name')->get();
    }

    protected function wrongTotalSubquery(string $payrollBankColumn): Builder
    {
        return WrongDeduction::query()
            ->selectRaw('coalesce(sum(amount), 0)')
            ->whereColumn('wrong_deductions.payroll_bank_id', $payrollBankColumn)
            ->whereIn('status', [
                InstallmentRecordStatus::Legacy->value,
                InstallmentRecordStatus::Open->value,
            ]);
    }
}
