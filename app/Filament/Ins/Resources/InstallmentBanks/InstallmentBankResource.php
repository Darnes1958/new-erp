<?php

namespace App\Filament\Ins\Resources\InstallmentBanks;

use App\Filament\Ins\Resources\InstallmentBanks\Pages\CreateInstallmentBank;
use App\Filament\Ins\Resources\InstallmentBanks\Pages\EditInstallmentBank;
use App\Filament\Ins\Resources\InstallmentBanks\Pages\ListInstallmentBanks;
use App\Filament\Ins\Resources\InstallmentBanks\Schemas\InstallmentBankForm;
use App\Filament\Ins\Resources\InstallmentBanks\Tables\InstallmentBanksTable;
use App\Filament\Ins\Support\BankResourceAccess;
use App\Models\InstallmentBank;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InstallmentBankResource extends Resource
{
    protected static ?string $model = InstallmentBank::class;

    protected static ?string $navigationLabel = 'فروع المصارف';

    protected static ?string $modelLabel = 'فرع مصرف';

    protected static ?string $pluralModelLabel = 'فروع المصارف';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'مصارف';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return BankResourceAccess::canManage();
    }

    public static function form(Schema $schema): Schema
    {
        return InstallmentBankForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstallmentBanksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstallmentBanks::route('/'),
            'create' => CreateInstallmentBank::route('/create'),
            'edit' => EditInstallmentBank::route('/{record}/edit'),
        ];
    }
}
