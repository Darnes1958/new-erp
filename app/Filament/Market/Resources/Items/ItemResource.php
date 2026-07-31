<?php

namespace App\Filament\Market\Resources\Items;

use App\Filament\Market\Resources\Items\Pages\CreateItem;
use App\Filament\Market\Resources\Items\Pages\EditItem;
use App\Filament\Market\Resources\Items\Pages\ListItems;
use App\Filament\Market\Resources\Items\Schemas\ItemForm;
use App\Filament\Market\Resources\Items\Tables\ItemsTable;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\Item;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static ?string $navigationLabel = 'أصناف';

    protected static ?string $modelLabel = 'صنف';

    protected static ?string $pluralModelLabel = 'أصناف';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::WarehousesItems;

    protected static ?int $navigationSort = 1;

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
