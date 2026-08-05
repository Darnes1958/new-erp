<?php

namespace App\Services\Installments;

use App\Models\InstallmentDeduction;
use App\Models\PayrollBank;
use App\Models\Warehouse;
use App\Support\BankCommissionCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InstallmentBranchCommissionReportService
{
    public function reportTitle(?int $warehouseId, ?string $dateFrom, ?string $dateTo): string
    {
        $warehouseName = $warehouseId
            ? (Warehouse::query()->whereKey($warehouseId)->value('name') ?? '—')
            : '—';

        return "عمولة الفروع - {$warehouseName} من تاريخ : {$dateFrom} إلي : {$dateTo}";
    }

    public function reportQuery(?int $warehouseId, ?string $dateFrom, ?string $dateTo): Builder
    {
        if (! $warehouseId) {
            return PayrollBank::query()->whereRaw('1 = 0');
        }

        return PayrollBank::query()
            ->with('bankMain')
            ->select('payroll_banks.*')
            ->selectSub($this->collectedTotalSubquery($warehouseId, $dateFrom, $dateTo), 'collected_total')
            ->selectSub($this->installmentsCountSubquery($warehouseId, $dateFrom, $dateTo), 'installments_count')
            ->whereExists(function ($query) use ($warehouseId, $dateFrom, $dateTo): void {
                $query->selectRaw('1')
                    ->from('installment_deductions')
                    ->join(
                        'installment_contracts',
                        'installment_contracts.id',
                        '=',
                        'installment_deductions.installment_contract_id',
                    )
                    ->join(
                        'sales_invoices',
                        'sales_invoices.id',
                        '=',
                        'installment_contracts.sales_invoice_id',
                    )
                    ->where('sales_invoices.warehouse_id', $warehouseId)
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
    public function exportRows(?int $warehouseId, ?string $dateFrom, ?string $dateTo): Collection
    {
        return $this->reportQuery($warehouseId, $dateFrom, $dateTo)
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
    public function summary(?int $warehouseId, ?string $dateFrom, ?string $dateTo): array
    {
        return $this->summaryFromRows($this->exportRows($warehouseId, $dateFrom, $dateTo));
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

    protected function collectedTotalSubquery(int $warehouseId, ?string $dateFrom, ?string $dateTo): Builder
    {
        return $this->applyPeriodScope(
            $this->deductionsForWarehouseQuery($warehouseId)
                ->selectRaw('coalesce(sum(installment_deductions.deducted_amount), 0)'),
            $dateFrom,
            $dateTo,
        );
    }

    protected function installmentsCountSubquery(int $warehouseId, ?string $dateFrom, ?string $dateTo): Builder
    {
        return $this->applyPeriodScope(
            $this->deductionsForWarehouseQuery($warehouseId)
                ->selectRaw('count(*)'),
            $dateFrom,
            $dateTo,
        );
    }

    protected function deductionsForWarehouseQuery(int $warehouseId): Builder
    {
        return InstallmentDeduction::query()
            ->join(
                'installment_contracts',
                'installment_contracts.id',
                '=',
                'installment_deductions.installment_contract_id',
            )
            ->join(
                'sales_invoices',
                'sales_invoices.id',
                '=',
                'installment_contracts.sales_invoice_id',
            )
            ->where('sales_invoices.warehouse_id', $warehouseId)
            ->whereColumn('installment_contracts.payroll_bank_id', 'payroll_banks.id');
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
