<?php

namespace App\Filament\Market\Resources\WarehouseTransfers;

use App\Filament\Market\Resources\WarehouseTransfers\Pages\ListWarehouseTransfers;
use App\Filament\Market\Resources\WarehouseTransfers\Tables\WarehouseTransfersTable;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\WarehouseTransfer;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class WarehouseTransferResource extends Resource
{
    protected static ?string $model = WarehouseTransfer::class;

    protected static ?string $navigationLabel = 'نقل أصناف بين المخازن والمعارض';

    protected static ?string $modelLabel = 'إذن نقل';

    protected static ?string $pluralModelLabel = 'أذون النقل';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::WarehousesItems;

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->is_prog) {
            return true;
        }

        return $user->can('نقل أصناف');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function table(Table $table): Table
    {
        return WarehouseTransfersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouseTransfers::route('/'),
        ];
    }
}
