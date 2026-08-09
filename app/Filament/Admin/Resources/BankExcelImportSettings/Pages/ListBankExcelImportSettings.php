<?php

namespace App\Filament\Admin\Resources\BankExcelImportSettings\Pages;

use App\Filament\Admin\Resources\BankExcelImportSettings\BankExcelImportSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBankExcelImportSettings extends ListRecords
{
    protected static string $resource = BankExcelImportSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('إضافة'),
        ];
    }
}
