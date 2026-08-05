<?php

namespace App\Filament\Ins\Resources\BankMains;

use App\Filament\Ins\Resources\BankMains\Pages\CreateBankMain;
use App\Filament\Ins\Resources\BankMains\Pages\EditBankMain;
use App\Filament\Ins\Resources\BankMains\Pages\ListBankMains;
use App\Filament\Ins\Resources\BankMains\Schemas\BankMainForm;
use App\Filament\Ins\Resources\BankMains\Tables\BankMainsTable;
use App\Models\BankMain;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class BankMainResource extends Resource
{
    protected static ?string $model = BankMain::class;

    protected static ?string $navigationLabel = 'المصرف الأم';

    protected static ?string $modelLabel = 'مصرف أم';

    protected static ?string $pluralModelLabel = 'المصرف الأم';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = 'مصارف';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_prog;
    }

    public static function form(Schema $schema): Schema
    {
        return BankMainForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankMainsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankMains::route('/'),
            'create' => CreateBankMain::route('/create'),
            'edit' => EditBankMain::route('/{record}/edit'),
        ];
    }
}
