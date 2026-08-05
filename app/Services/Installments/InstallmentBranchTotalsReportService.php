<?php

namespace App\Services\Installments;

use App\Enums\InstallmentRecordStatus;
use App\Models\PayrollBank;
use App\Models\Warehouse;
use App\Models\WrongDeduction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InstallmentBranchTotalsReportService
{
    public function reportTitle(?int $warehouseId): string
    {
        $warehouseName = $warehouseId
            ? (Warehouse::query()->whereKey($warehouseId)->value('name') ?? '—')
            : '—';

        return "إجمالي الفروع - {$warehouseName}";
    }

    public function reportQuery(?int $warehouseId): Builder
    {
        if (! $warehouseId) {
            return PayrollBank::query()->whereRaw('1 = 0');
        }

        return PayrollBank::query()
            ->whereHas('installmentContracts', fn (Builder $query): Builder => $this->scopeContractsToWarehouse($query, $warehouseId))
            ->withCount([
                'installmentContracts as contracts_count' => fn (Builder $query): Builder => $this->scopeContractsToWarehouse($query, $warehouseId),
            ])
            ->withSum([
                'installmentContracts as contracts_total' => fn (Builder $query): Builder => $this->scopeContractsToWarehouse($query, $warehouseId),
            ], 'contract_total')
            ->withSum([
                'installmentContracts as total_paid' => fn (Builder $query): Builder => $this->scopeContractsToWarehouse($query, $warehouseId),
            ], 'total_paid')
            ->withSum([
                'installmentContracts as balance_total' => fn (Builder $query): Builder => $this->scopeContractsToWarehouse($query, $warehouseId),
            ], 'balance')
            ->withSum([
                'installmentContracts as surplus_total' => fn (Builder $query): Builder => $this->scopeContractsToWarehouse($query, $warehouseId),
            ], 'surplus_amount')
            ->withSum([
                'installmentContracts as suspended_total' => fn (Builder $query): Builder => $this->scopeContractsToWarehouse($query, $warehouseId),
            ], 'suspended_amount')
            ->selectSub($this->wrongTotalSubquery($warehouseId), 'wrong_total');
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
    public function summary(?int $warehouseId): array
    {
        $rows = $this->reportQuery($warehouseId)->get();

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
     * @return Collection<int, PayrollBank>
     */
    public function exportRows(?int $warehouseId): Collection
    {
        return $this->reportQuery($warehouseId)->orderBy('name')->get();
    }

    protected function scopeContractsToWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->whereHas(
            'salesInvoice',
            fn (Builder $invoiceQuery): Builder => $invoiceQuery->where('warehouse_id', $warehouseId),
        );
    }

    protected function wrongTotalSubquery(int $warehouseId): Builder
    {
        return WrongDeduction::query()
            ->selectRaw('coalesce(sum(wrong_deductions.amount), 0)')
            ->whereColumn('wrong_deductions.payroll_bank_id', 'payroll_banks.id')
            ->whereIn('wrong_deductions.status', [
                InstallmentRecordStatus::Legacy->value,
                InstallmentRecordStatus::Open->value,
            ])
            ->whereExists(function ($query) use ($warehouseId): void {
                $query->selectRaw('1')
                    ->from('installment_contracts')
                    ->join('sales_invoices', 'sales_invoices.id', '=', 'installment_contracts.sales_invoice_id')
                    ->whereColumn('installment_contracts.payroll_bank_id', 'payroll_banks.id')
                    ->where('sales_invoices.warehouse_id', $warehouseId);
            });
    }
}
