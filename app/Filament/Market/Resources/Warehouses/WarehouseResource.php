<?php

namespace App\Filament\Market\Resources\Warehouses;

use App\Filament\Market\Resources\Warehouses\Pages\CreateWarehouse;
use App\Filament\Market\Resources\Warehouses\Pages\EditWarehouse;
use App\Filament\Market\Resources\Warehouses\Pages\ListWarehouses;
use App\Filament\Market\Resources\Warehouses\Schemas\WarehouseForm;
use App\Filament\Market\Resources\Warehouses\Tables\WarehousesTable;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\Warehouse;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationLabel = 'مخازن';

    protected static ?string $modelLabel = 'مخزن';

    protected static ?string $pluralModelLabel = 'مخازن';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::WarehousesItems;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return WarehouseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehousesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouses::route('/'),
            'create' => CreateWarehouse::route('/create'),
            'edit' => EditWarehouse::route('/{record}/edit'),
        ];
    }
}
