<?php

namespace App\Filament\Ins\Resources\InstallmentReturns;

use App\Filament\Ins\Resources\InstallmentReturns\Pages\ListInstallmentReturns;
use App\Filament\Ins\Resources\InstallmentReturns\Tables\InstallmentReturnsTable;
use App\Models\InstallmentSuspended;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InstallmentReturnResource extends Resource
{
    protected static ?string $model = InstallmentSuspended::class;

    protected static ?string $navigationLabel = 'أقساط مرجعة';

    protected static ?string $modelLabel = 'ترجيع';

    protected static ?string $pluralModelLabel = 'ترجيع';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static string|\UnitEnum|null $navigationGroup = 'خصومات ومدفوعات';

    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        return (string) InstallmentSuspended::query()->count();
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
        return InstallmentReturnsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstallmentReturns::route('/'),
        ];
    }
}
