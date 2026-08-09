<?php

namespace App\Filament\Market\Resources\InventoryCountSessions;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Market\Resources\InventoryCountSessions\Pages\CreateInventoryCountSession;
use App\Filament\Market\Resources\InventoryCountSessions\Pages\EditInventoryCountSession;
use App\Filament\Market\Resources\InventoryCountSessions\Pages\ListInventoryCountSessions;
use App\Filament\Market\Resources\InventoryCountSessions\Schemas\InventoryCountSessionForm;
use App\Filament\Market\Resources\InventoryCountSessions\Tables\InventoryCountSessionsTable;
use App\Models\InventoryCountSession;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InventoryCountSessionResource extends Resource
{
    protected static bool $isDiscovered = false;

    protected static ?string $model = InventoryCountSession::class;

    protected static ?string $navigationLabel = 'التجهيز للجرد';

    protected static ?string $modelLabel = 'جلسة جرد';

    protected static ?string $pluralModelLabel = 'جلسات الجرد';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = AdminNavigationGroup::Management;

    protected static ?string $navigationParentItem = 'جرد';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->hasRole('admin');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return InventoryCountSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryCountSessionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryCountSessions::route('/'),
            'create' => CreateInventoryCountSession::route('/create'),
            'edit' => EditInventoryCountSession::route('/{record}/edit'),
        ];
    }
}
