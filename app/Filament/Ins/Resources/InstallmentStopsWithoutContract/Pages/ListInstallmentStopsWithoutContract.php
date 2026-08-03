<?php

namespace App\Filament\Ins\Resources\InstallmentStopsWithoutContract\Pages;

use App\Filament\Ins\Resources\InstallmentStopsWithoutContract\InstallmentStopWithoutContractResource;
use App\Filament\Ins\Support\InstallmentListPrintActions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstallmentStopsWithoutContract extends ListRecords
{
    protected static string $resource = InstallmentStopWithoutContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InstallmentListPrintActions::stopsWithoutContractPdf(),
            InstallmentListPrintActions::stopsWithoutContractExcel(),
            CreateAction::make()->label('إضافة'),
        ];
    }
}
