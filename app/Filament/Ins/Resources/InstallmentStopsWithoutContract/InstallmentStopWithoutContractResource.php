<?php

namespace App\Filament\Ins\Resources\InstallmentStopsWithoutContract;

use App\Filament\Ins\Resources\InstallmentStopsWithoutContract\Pages\CreateInstallmentStopWithoutContract;
use App\Filament\Ins\Resources\InstallmentStopsWithoutContract\Pages\EditInstallmentStopWithoutContract;
use App\Filament\Ins\Resources\InstallmentStopsWithoutContract\Pages\ListInstallmentStopsWithoutContract;
use App\Filament\Ins\Resources\InstallmentStopsWithoutContract\Schemas\InstallmentStopWithoutContractForm;
use App\Filament\Ins\Resources\InstallmentStopsWithoutContract\Tables\InstallmentStopsWithoutContractTable;
use App\Models\InstallmentStopWithoutContract;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InstallmentStopWithoutContractResource extends Resource
{
    protected static ?string $model = InstallmentStopWithoutContract::class;

    protected static ?string $navigationLabel = 'إيقاف خصم بدون عقد';

    protected static ?string $modelLabel = 'إيقاف بدون عقد';

    protected static ?string $pluralModelLabel = 'إيقاف خصم بدون عقد';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static string|\UnitEnum|null $navigationGroup = 'خصومات ومدفوعات';

    protected static ?int $navigationSort = 8;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) InstallmentStopWithoutContract::query()->count();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('ادخال عقود') || $user?->can('تعديل عقود');
    }

    public static function form(Schema $schema): Schema
    {
        return InstallmentStopWithoutContractForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstallmentStopsWithoutContractTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstallmentStopsWithoutContract::route('/'),
            'create' => CreateInstallmentStopWithoutContract::route('/create'),
            'edit' => EditInstallmentStopWithoutContract::route('/{record}/edit'),
        ];
    }
}
