<?php

namespace App\Filament\Finance\Resources\Expenses\Concerns;

use App\Services\Excel\FinanceExcelService;
use App\Services\Finance\ExpenseListService;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

trait InteractsWithExpenseListExports
{
    protected function downloadExpenseListExcel(): mixed
    {
        $rows = $this->exportExpenseListRows();

        if ($rows === null) {
            return null;
        }

        return app(FinanceExcelService::class)->expensesList(
            $rows,
            app(ExpenseListService::class)->exportMeta($this),
        );
    }

    /**
     * @return Collection<int, \App\Models\Expense>|null
     */
    protected function exportExpenseListRows(): ?Collection
    {
        $rows = $this->getTableQueryForExport()
            ->with(['expenseType', 'bankAccount', 'cashBox', 'warehouse'])
            ->get();

        if ($rows->isEmpty()) {
            Notification::make()
                ->title('لا توجد بيانات للتصدير')
                ->warning()
                ->send();

            return null;
        }

        return $rows;
    }
}
