<?php

namespace App\Filament\Admin\Resources\BankExcelImportSettings\Pages;

use App\Filament\Admin\Resources\BankExcelImportSettings\BankExcelImportSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBankExcelImportSetting extends EditRecord
{
    protected static string $resource = BankExcelImportSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
