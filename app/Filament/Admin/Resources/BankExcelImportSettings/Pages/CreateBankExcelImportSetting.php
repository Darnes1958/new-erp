<?php

namespace App\Filament\Admin\Resources\BankExcelImportSettings\Pages;

use App\Filament\Admin\Resources\BankExcelImportSettings\BankExcelImportSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBankExcelImportSetting extends CreateRecord
{
    protected static string $resource = BankExcelImportSettingResource::class;

    protected static ?string $title = 'إعداد استيراد جديد';
}
