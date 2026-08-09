<?php

namespace App\Services\Finance;

use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Warehouse;
use App\Support\ErpNumber;
use App\Support\Utf8Text;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;

class ExpenseListService
{
    /** @return list<string> */
    public function headers(): array
    {
        return [
            'التاريخ',
            'البيان',
            'المصرف',
            'الخزينة',
            'المكان',
            'المبلغ',
            'ملاحظات',
        ];
    }

    /**
     * @return array{
     *     dateFrom: ?string,
     *     dateTo: ?string,
     *     expenseTypeName: ?string,
     *     warehouseName: ?string
     * }
     */
    public function exportMeta(ListRecords $page): array
    {
        $dateFilter = $page->getTableFilterState('expense_date') ?? [];
        $expenseTypeId = data_get($page->getTableFilterState('expense_type_id'), 'value');
        $warehouseId = data_get($page->getTableFilterState('warehouse_id'), 'value');

        return [
            'dateFrom' => $dateFilter['date_from'] ?? null,
            'dateTo' => $dateFilter['date_to'] ?? null,
            'expenseTypeName' => filled($expenseTypeId)
                ? ExpenseType::query()->whereKey($expenseTypeId)->value('name')
                : null,
            'warehouseName' => filled($warehouseId)
                ? Warehouse::query()->whereKey($warehouseId)->value('name')
                : null,
        ];
    }

    /** @return array<int, string|float|null> */
    public function mapExcelRow(Expense $expense): array
    {
        return [
            $expense->expense_date?->format('Y-m-d'),
            Utf8Text::clean($expense->expenseType?->name),
            Utf8Text::clean($expense->bankAccount?->name),
            Utf8Text::clean($expense->cashBox?->name),
            Utf8Text::clean($expense->warehouse?->name),
            (float) $expense->amount,
            Utf8Text::clean($expense->notes),
        ];
    }

    /**
     * @param  Collection<int, Expense>  $rows
     * @return list<string|float>
     */
    public function totalsRow(Collection $rows): array
    {
        return [
            '',
            '',
            '',
            '',
            'الإجمالي',
            round((float) $rows->sum(fn (Expense $expense): float => (float) $expense->amount), 3),
            '',
        ];
    }

    /**
     * @param  Collection<int, Expense>  $rows
     * @return list<string>
     */
    public function displayTotalsRow(Collection $rows): array
    {
        $totals = $this->totalsRow($rows);
        $totals[5] = ErpNumber::money($totals[5]);

        return $totals;
    }
}
