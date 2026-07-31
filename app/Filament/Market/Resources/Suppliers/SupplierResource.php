<?php

namespace App\Filament\Market\Resources\Suppliers;

use App\Filament\Market\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Market\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Market\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Market\Resources\Suppliers\Schemas\SupplierForm;
use App\Filament\Market\Resources\Suppliers\Tables\SuppliersTable;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\Supplier;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationLabel = 'موردين';

    protected static ?string $modelLabel = 'مورد';

    protected static ?string $pluralModelLabel = 'موردين';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::CustomersSuppliers;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return SupplierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuppliersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
