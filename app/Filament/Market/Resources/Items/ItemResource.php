<?php

namespace App\Filament\Market\Resources\Items;

use App\Filament\Market\Resources\Items\Pages\CreateItem;
use App\Filament\Market\Resources\Items\Pages\EditItem;
use App\Filament\Market\Resources\Items\Pages\ListItems;
use App\Filament\Market\Resources\Items\Schemas\ItemForm;
use App\Filament\Market\Resources\Items\Tables\ItemsTable;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Filament\Support\AuthorizesErpResource;
use App\Models\Item;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ItemResource extends Resource
{
    use AuthorizesErpResource;

    protected static ?string $model = Item::class;

    protected static ?string $navigationLabel = 'أصناف';

    protected static ?string $modelLabel = 'صنف';

    protected static ?string $pluralModelLabel = 'أصناف';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::WarehousesItems;

    protected static ?int $navigationSort = 1;

    protected static function createPermissions(): array
    {
        return ['المخزون', 'ادخال مخازن', 'تعديل مخازن'];
    }

    protected static function deletePermissions(): array
    {
        return ['الغاء مخازن'];
    }

    protected static function accessPermissions(): array
    {
        return ['المخزون', 'ادخال مخازن', 'تعديل مخازن', 'الغاء مخازن', 'تقارير مخزون', 'تقارير مخازن', 'استفسار عن الاسعار'];
    }

    public static function form(Schema $schema): Schema
    {
        return ItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItems::route('/'),
            'create' => CreateItem::route('/create'),
            'edit' => EditItem::route('/{record}/edit'),
        ];
    }
}
