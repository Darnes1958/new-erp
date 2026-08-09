<?php

namespace App\Filament\Market\Resources\InventoryCountLines;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Market\Resources\InventoryCountLines\Pages\CreateInventoryCountLine;
use App\Filament\Market\Resources\InventoryCountLines\Pages\ListInventoryCountLines;
use App\Filament\Market\Resources\InventoryCountLines\Schemas\InventoryCountLineForm;
use App\Filament\Market\Resources\InventoryCountLines\Tables\InventoryCountLinesTable;
use App\Models\InventoryCountSession;
use App\Models\InventoryCountLine;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InventoryCountLineResource extends Resource
{
    protected static bool $isDiscovered = false;

    protected static ?string $model = InventoryCountLine::class;

    protected static ?string $navigationLabel = 'جرد';

    protected static ?string $modelLabel = 'سطر جرد';

    protected static ?string $pluralModelLabel = 'جرد';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = AdminNavigationGroup::Management;

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        if (! ($user?->is_prog || $user?->hasRole('admin'))) {
            return false;
        }

        return InventoryCountSession::query()->where('is_active', true)->exists();
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return InventoryCountLineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryCountLinesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryCountLines::route('/'),
            'create' => CreateInventoryCountLine::route('/create'),
        ];
    }
}
