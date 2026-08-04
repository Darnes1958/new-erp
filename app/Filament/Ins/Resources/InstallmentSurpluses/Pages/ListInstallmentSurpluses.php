<?php

namespace App\Filament\Ins\Resources\InstallmentSurpluses\Pages;

use App\Filament\Ins\Resources\InstallmentSurpluses\InstallmentSurplusResource;
use App\Filament\Ins\Support\Concerns\HasInstallmentExportHeaderLayout;
use App\Filament\Ins\Support\InstallmentListPrintActions;
use Filament\Resources\Pages\ListRecords;

class ListInstallmentSurpluses extends ListRecords
{
    use HasInstallmentExportHeaderLayout;

    protected static string $resource = InstallmentSurplusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InstallmentListPrintActions::installmentSurplusesExports(),
            InstallmentListPrintActions::create(),
        ];
    }
}
