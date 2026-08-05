<?php

namespace App\Filament\Ins\Resources\InstallmentSurpluses;

use App\Filament\Ins\Resources\InstallmentSurpluses\Pages\CreateInstallmentSurplus;
use App\Filament\Ins\Resources\InstallmentSurpluses\Pages\EditInstallmentSurplus;
use App\Filament\Ins\Resources\InstallmentSurpluses\Pages\ListInstallmentSurpluses;
use App\Filament\Ins\Resources\InstallmentSurpluses\Tables\InstallmentSurplusesTable;
use App\Models\InstallmentSurplus;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InstallmentSurplusResource extends Resource
{
    protected static ?string $model = InstallmentSurplus::class;

    protected static ?string $navigationLabel = 'خصم بالفائض';

    protected static ?string $modelLabel = 'فائض';

    protected static ?string $pluralModelLabel = 'فائض';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-plus-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'خصومات ومدفوعات';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        return (string) InstallmentSurplus::query()->count();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('ادخال عقود') || $user?->can('تعديل عقود');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return InstallmentSurplusesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstallmentSurpluses::route('/'),
            'create' => CreateInstallmentSurplus::route('/create'),
            'edit' => EditInstallmentSurplus::route('/{record}/edit'),
        ];
    }
}
