<?php

namespace App\Filament\Ins\Resources\PayrollBanks;

use App\Filament\Ins\Resources\PayrollBanks\Pages\CreatePayrollBank;
use App\Filament\Ins\Resources\PayrollBanks\Pages\EditPayrollBank;
use App\Filament\Ins\Resources\PayrollBanks\Pages\ListPayrollBanks;
use App\Filament\Ins\Resources\PayrollBanks\Schemas\PayrollBankForm;
use App\Filament\Ins\Resources\PayrollBanks\Tables\PayrollBanksTable;
use App\Filament\Ins\Support\BankResourceAccess;
use App\Models\PayrollBank;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PayrollBankResource extends Resource
{
    protected static ?string $model = PayrollBank::class;

    protected static ?string $navigationLabel = 'الحسابات التجميعية';

    protected static ?string $modelLabel = 'حساب تجميعي';

    protected static ?string $pluralModelLabel = 'الحسابات التجميعية';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'مصارف';

    protected static ?int $navigationSort = 2;

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
        return PayrollBankForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayrollBanksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollBanks::route('/'),
            'create' => CreatePayrollBank::route('/create'),
            'edit' => EditPayrollBank::route('/{record}/edit'),
        ];
    }
}
