<?php

namespace App\Filament\Ins\Resources\WrongDeductions\Pages;

use App\Filament\Ins\Resources\WrongDeductions\WrongDeductionResource;
use App\Filament\Ins\Support\InstallmentListPrintActions;
use Filament\Resources\Pages\ListRecords;

class ListWrongDeductions extends ListRecords
{
    protected static string $resource = WrongDeductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InstallmentListPrintActions::wrongDeductionsPdf(),
            InstallmentListPrintActions::wrongDeductionsExcel(),
        ];
    }
}
