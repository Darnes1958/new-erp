<?php

namespace App\Filament\Finance\Resources\Expenses\Pages;

use App\Filament\Finance\Resources\Expenses\Concerns\InteractsWithExpenseListExports;
use App\Filament\Finance\Resources\Expenses\ExpenseResource;
use App\Filament\Ins\Support\InstallmentListPrintActions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExpenses extends ListRecords
{
    use InteractsWithExpenseListExports;

    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadExpenseListExcel()),
        ];
    }
}
