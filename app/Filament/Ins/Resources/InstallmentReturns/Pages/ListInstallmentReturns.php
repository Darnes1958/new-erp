<?php

namespace App\Filament\Ins\Resources\InstallmentReturns\Pages;

use App\Filament\Ins\Resources\InstallmentReturns\InstallmentReturnResource;
use App\Filament\Ins\Support\Concerns\HasInstallmentExportHeaderLayout;
use App\Filament\Ins\Support\InstallmentListPrintActions;
use Filament\Resources\Pages\ListRecords;

class ListInstallmentReturns extends ListRecords
{
    use HasInstallmentExportHeaderLayout;

    protected static string $resource = InstallmentReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InstallmentListPrintActions::installmentReturnsExports(),
        ];
    }
}
