<?php

namespace App\Filament\Ins\Resources\InstallmentCancelledContracts;

use App\Filament\Ins\Resources\InstallmentCancelledContracts\Pages\ListInstallmentCancelledContracts;
use App\Filament\Ins\Resources\InstallmentCancelledContracts\Pages\ViewInstallmentCancelledContract;
use App\Filament\Ins\Resources\InstallmentCancelledContracts\Schemas\InstallmentCancelledContractInfolist;
use App\Filament\Ins\Resources\InstallmentCancelledContracts\Tables\InstallmentCancelledContractsTable;
use App\Models\InstallmentCancelledContract;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class InstallmentCancelledContractResource extends Resource
{
    protected static ?string $model = InstallmentCancelledContract::class;

    protected static ?string $navigationLabel = 'عقود ملغية';

    protected static ?string $modelLabel = 'عقد ملغي';

    protected static ?string $pluralModelLabel = 'عقود ملغية بعد التعاقد';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-x-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'خصومات ومدفوعات';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InstallmentCancelledContractInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstallmentCancelledContractsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstallmentCancelledContracts::route('/'),
            'view' => ViewInstallmentCancelledContract::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
