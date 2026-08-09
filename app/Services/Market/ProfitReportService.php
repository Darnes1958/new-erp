<?php

namespace App\Services\Market;

use App\Models\Expense;
use App\Models\InventoryCountLine;
use App\Models\RentTransaction;
use App\Models\SalaryTransaction;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use Illuminate\Support\Collection;

class ProfitReportService
{
    /**
     * @return array<int, string>
     */
    public function availableYears(): array
    {
        $years = SalesInvoice::query()
            ->selectRaw('DISTINCT YEAR(invoice_date) AS year')
            ->orderByDesc('year')
            ->pluck('year', 'year')
            ->all();

        if ($years === []) {
            $years[(int) now()->format('Y')] = (int) now()->format('Y');
        }

        return $years;
    }

    public function adminExpensesTotal(int $year): float
    {
        return (float) Expense::query()
            ->whereYear('expense_date', $year)
            ->whereNull('warehouse_id')
            ->sum('amount');
    }

    public function hasInventoryCountForYear(int $year, ?int $warehouseId = null): bool
    {
        return InventoryCountLine::query()
            ->whereHas('session', fn ($query) => $query->where('year', $year))
            ->when(
                filled($warehouseId),
                fn ($query) => $query->where('warehouse_id', $warehouseId),
            )
            ->exists();
    }

    public function inventorySurplusTotal(int $year, ?int $warehouseId = null): float
    {
        return (float) InventoryCountLine::query()
            ->where('value_amount', '>=', 0)
            ->whereHas('session', fn ($query) => $query->where('year', $year))
            ->when(
                filled($warehouseId),
                fn ($query) => $query->where('warehouse_id', $warehouseId),
            )
            ->sum('value_amount');
    }

    public function inventoryDeficitTotal(int $year, ?int $warehouseId = null): float
    {
        return (float) InventoryCountLine::query()
            ->where('value_amount', '<', 0)
            ->whereHas('session', fn ($query) => $query->where('year', $year))
            ->when(
                filled($warehouseId),
                fn ($query) => $query->where('warehouse_id', $warehouseId),
            )
            ->sum('value_amount');
    }

    /**
     * @return Collection<int, array{
     *     month: int,
     *     month_name: string,
     *     rebh: float,
     *     masr: float,
     *     sal: float,
     *     rent: float,
     *     ksm: float,
     *     safi: float
     * }>
     */
    public function monthlySummary(int $year, ?int $warehouseId = null): Collection
    {
        /** @var array<int, array{month: int, month_name: string, rebh: float, masr: float, sal: float, rent: float, ksm: float, safi: float}> $rows */
        $rows = [];

        for ($month = 1; $month <= 12; $month++) {
            $rows[$month] = [
                'month' => $month,
                'month_name' => $this->monthName($month),
                'rebh' => 0.0,
                'masr' => 0.0,
                'sal' => 0.0,
                'rent' => 0.0,
                'ksm' => 0.0,
                'safi' => 0.0,
            ];
        }

        foreach ($this->profitByMonth($year, $warehouseId) as $month => $amount) {
            $rows[$month]['rebh'] = round((float) $amount, 3);
        }

        foreach ($this->discountsByMonth($year, $warehouseId) as $month => $amount) {
            $rows[$month]['ksm'] = round((float) $amount, 3);
        }

        foreach ($this->expensesByMonth($year, $warehouseId) as $month => $amount) {
            $rows[$month]['masr'] = round((float) $amount, 3);
        }

        foreach ($this->salariesByMonth($year, $warehouseId) as $month => $amount) {
            $rows[$month]['sal'] = round((float) $amount, 3);
        }

        foreach ($this->rentsByMonth($year, $warehouseId) as $month => $amount) {
            $rows[$month]['rent'] = round((float) $amount, 3);
        }

        return collect($rows)
            ->map(function (array $row): array {
                $row['safi'] = round(
                    $row['rebh'] - $row['masr'] - $row['sal'] - $row['rent'] - $row['ksm'],
                    3,
                );

                return $row;
            })
            ->values();
    }

    /**
     * @return Collection<int, float>
     */
    protected function profitByMonth(int $year, ?int $warehouseId): Collection
    {
        return SalesInvoiceLine::query()
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_lines.sales_invoice_id')
            ->whereYear('sales_invoices.invoice_date', $year)
            ->when(
                filled($warehouseId),
                fn ($query) => $query->where('sales_invoices.warehouse_id', $warehouseId),
            )
            ->selectRaw('MONTH(sales_invoices.invoice_date) AS month')
            ->selectRaw('COALESCE(SUM(sales_invoice_lines.profit), 0) AS total')
            ->groupByRaw('MONTH(sales_invoices.invoice_date)')
            ->pluck('total', 'month');
    }

    /**
     * @return Collection<int, float>
     */
    protected function discountsByMonth(int $year, ?int $warehouseId): Collection
    {
        return SalesInvoice::query()
            ->whereYear('invoice_date', $year)
            ->when(
                filled($warehouseId),
                fn ($query) => $query->where('warehouse_id', $warehouseId),
            )
            ->selectRaw('MONTH(invoice_date) AS month')
            ->selectRaw('COALESCE(SUM(discount), 0) AS total')
            ->groupByRaw('MONTH(invoice_date)')
            ->pluck('total', 'month');
    }

    /**
     * @return Collection<int, float>
     */
    protected function expensesByMonth(int $year, ?int $warehouseId): Collection
    {
        return Expense::query()
            ->whereYear('expense_date', $year)
            ->when(
                filled($warehouseId),
                fn ($query) => $query->where('warehouse_id', $warehouseId),
            )
            ->selectRaw('MONTH(expense_date) AS month')
            ->selectRaw('COALESCE(SUM(amount), 0) AS total')
            ->groupByRaw('MONTH(expense_date)')
            ->pluck('total', 'month');
    }

    /**
     * @return Collection<int, float>
     */
    protected function salariesByMonth(int $year, ?int $warehouseId): Collection
    {
        return SalaryTransaction::query()
            ->join('salary_profiles', 'salary_profiles.id', '=', 'salary_transactions.salary_profile_id')
            ->whereYear('salary_transactions.transaction_date', $year)
            ->when(
                filled($warehouseId),
                fn ($query) => $query->where('salary_profiles.warehouse_id', $warehouseId),
            )
            ->selectRaw('MONTH(salary_transactions.transaction_date) AS month')
            ->selectRaw('COALESCE(SUM(salary_transactions.amount), 0) AS total')
            ->groupByRaw('MONTH(salary_transactions.transaction_date)')
            ->pluck('total', 'month');
    }

    /**
     * @return Collection<int, float>
     */
    protected function rentsByMonth(int $year, ?int $warehouseId): Collection
    {
        return RentTransaction::query()
            ->join('rent_profiles', 'rent_profiles.id', '=', 'rent_transactions.rent_profile_id')
            ->whereYear('rent_transactions.transaction_date', $year)
            ->when(
                filled($warehouseId),
                fn ($query) => $query->where('rent_profiles.warehouse_id', $warehouseId),
            )
            ->selectRaw('MONTH(rent_transactions.transaction_date) AS month')
            ->selectRaw('COALESCE(SUM(rent_transactions.amount), 0) AS total')
            ->groupByRaw('MONTH(rent_transactions.transaction_date)')
            ->pluck('total', 'month');
    }

    protected function monthName(int $month): string
    {
        return match ($month) {
            1 => 'يناير',
            2 => 'فبراير',
            3 => 'مارس',
            4 => 'أبريل',
            5 => 'مايو',
            6 => 'يونيو',
            7 => 'يوليو',
            8 => 'أغسطس',
            9 => 'سبتمبر',
            10 => 'أكتوبر',
            11 => 'نوفمبر',
            12 => 'ديسمبر',
            default => (string) $month,
        };
    }
}
