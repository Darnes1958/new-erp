<?php

namespace App\Services\Installments;

use App\Models\InstallmentDeduction;
use App\Models\PayrollBank;
use App\Support\BankCommissionCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InstallmentBankCommissionReportService
{
    public function reportTitle(?string $dateFrom, ?string $dateTo): string
    {
        return "تقرير عمولة المصارف من تاريخ : {$dateFrom} إلي : {$dateTo}";
    }

    public function reportQuery(?string $dateFrom, ?string $dateTo): Builder
    {
        return PayrollBank::query()
            ->with('bankMain')
            ->select('payroll_banks.*')
            ->selectSub($this->collectedTotalSubquery($dateFrom, $dateTo), 'collected_total')
            ->selectSub($this->installmentsCountSubquery($dateFrom, $dateTo), 'installments_count')
            ->whereExists(function ($query) use ($dateFrom, $dateTo): void {
                $query->selectRaw('1')
                    ->from('installment_deductions')
                    ->join(
                        'installment_contracts',
                        'installment_contracts.id',
                        '=',
                        'installment_deductions.installment_contract_id',
                    )
                    ->whereColumn('installment_contracts.payroll_bank_id', 'payroll_banks.id');

                if (filled($dateFrom)) {
                    $query->whereDate('installment_deductions.deduction_date', '>=', $dateFrom);
                }

                if (filled($dateTo)) {
                    $query->whereDate('installment_deductions.deduction_date', '<=', $dateTo);
                }
            });
    }

    /**
     * @return Collection<int, PayrollBank>
     */
    public function exportRows(?string $dateFrom, ?string $dateTo): Collection
    {
        return $this->reportQuery($dateFrom, $dateTo)
            ->orderBy('name')
            ->get();
    }

    public function commissionFor(PayrollBank $payrollBank): float
    {
        return BankCommissionCalculator::calculate(
            $payrollBank->bankMain,
            (float) ($payrollBank->collected_total ?? 0),
            (int) ($payrollBank->installments_count ?? 0),
        );
    }

    /**
     * @return array{installments_count: int, collected_total: float, commission_total: float}
     */
    public function summary(?string $dateFrom, ?string $dateTo): array
    {
        $rows = $this->exportRows($dateFrom, $dateTo);

        return [
            'installments_count' => (int) $rows->sum(fn (PayrollBank $row): int => (int) ($row->installments_count ?? 0)),
            'collected_total' => (float) $rows->sum(fn (PayrollBank $row): float => (float) ($row->collected_total ?? 0)),
            'commission_total' => (float) $rows->sum(fn (PayrollBank $row): float => $this->commissionFor($row)),
        ];
    }

    /**
     * @return array{installments_count: int, collected_total: float, commission_total: float}
     */
    public function summaryFromRows(Collection $rows): array
    {
        return [
            'installments_count' => (int) $rows->sum(fn (PayrollBank $row): int => (int) ($row->installments_count ?? 0)),
            'collected_total' => (float) $rows->sum(fn (PayrollBank $row): float => (float) ($row->collected_total ?? 0)),
            'commission_total' => (float) $rows->sum(fn (PayrollBank $row): float => $this->commissionFor($row)),
        ];
    }

    protected function collectedTotalSubquery(?string $dateFrom, ?string $dateTo): Builder
    {
        return $this->applyPeriodScope(
            InstallmentDeduction::query()
                ->selectRaw('coalesce(sum(installment_deductions.deducted_amount), 0)')
                ->join(
                    'installment_contracts',
                    'installment_contracts.id',
                    '=',
                    'installment_deductions.installment_contract_id',
                )
                ->whereColumn('installment_contracts.payroll_bank_id', 'payroll_banks.id'),
            $dateFrom,
            $dateTo,
        );
    }

    protected function installmentsCountSubquery(?string $dateFrom, ?string $dateTo): Builder
    {
        return $this->applyPeriodScope(
            InstallmentDeduction::query()
                ->selectRaw('count(*)')
                ->join(
                    'installment_contracts',
                    'installment_contracts.id',
                    '=',
                    'installment_deductions.installment_contract_id',
                )
                ->whereColumn('installment_contracts.payroll_bank_id', 'payroll_banks.id'),
            $dateFrom,
            $dateTo,
        );
    }

    protected function applyPeriodScope(Builder $query, ?string $dateFrom, ?string $dateTo): Builder
    {
        if (filled($dateFrom)) {
            $query->whereDate('installment_deductions.deduction_date', '>=', $dateFrom);
        }

        if (filled($dateTo)) {
            $query->whereDate('installment_deductions.deduction_date', '<=', $dateTo);
        }

        return $query;
    }
}
