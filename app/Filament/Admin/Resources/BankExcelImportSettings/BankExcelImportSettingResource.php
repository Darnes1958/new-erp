<?php

namespace App\Filament\Admin\Resources\BankExcelImportSettings;

use App\Filament\Admin\Resources\BankExcelImportSettings\Pages\CreateBankExcelImportSetting;
use App\Filament\Admin\Resources\BankExcelImportSettings\Pages\EditBankExcelImportSetting;
use App\Filament\Admin\Resources\BankExcelImportSettings\Pages\ListBankExcelImportSettings;
use App\Filament\Admin\Resources\BankExcelImportSettings\Schemas\BankExcelImportSettingForm;
use App\Filament\Admin\Resources\BankExcelImportSettings\Tables\BankExcelImportSettingsTable;
use App\Filament\Admin\Support\ProgrammerAccess;
use App\Models\BankExcelImportSetting;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BankExcelImportSettingResource extends Resource
{
    protected static ?string $model = BankExcelImportSetting::class;

    protected static ?string $navigationLabel = 'إعدادات المصارف';

    protected static ?string $modelLabel = 'إعداد استيراد';

    protected static ?string $pluralModelLabel = 'إعدادات استيراد Excel';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = 'استيراد Excel';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return ProgrammerAccess::allowed();
    }

    public static function form(Schema $schema): Schema
    {
        return BankExcelImportSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankExcelImportSettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankExcelImportSettings::route('/'),
            'create' => CreateBankExcelImportSetting::route('/create'),
            'edit' => EditBankExcelImportSetting::route('/{record}/edit'),
        ];
    }
}
