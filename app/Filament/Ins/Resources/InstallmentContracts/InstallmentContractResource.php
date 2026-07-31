<?php

namespace App\Filament\Ins\Resources\InstallmentContracts;

use App\Filament\Ins\Resources\InstallmentContracts\Pages\ListInstallmentContracts;
use App\Filament\Ins\Resources\InstallmentContracts\Pages\ViewInstallmentContract;
use App\Filament\Ins\Resources\InstallmentContracts\Schemas\InstallmentContractInfolist;
use App\Filament\Ins\Resources\InstallmentContracts\Tables\InstallmentContractsTable;
use App\Models\InstallmentContract;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class InstallmentContractResource extends Resource
{
    protected static ?string $model = InstallmentContract::class;

    protected static ?string $navigationLabel = 'عقود التقسيط';

    protected static ?string $modelLabel = 'عقد تقسيط';

    protected static ?string $pluralModelLabel = 'عقود التقسيط';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'عقود التقسيط';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return InstallmentContractInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstallmentContractsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstallmentContracts::route('/'),
            'view' => ViewInstallmentContract::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
