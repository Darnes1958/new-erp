<?php

namespace App\Filament\Ins\Resources\InstallmentContracts;

use App\Filament\Ins\Pages\EditInstallmentContract;
use App\Filament\Ins\Resources\InstallmentContracts\Pages\EditInstallmentContractRecord;
use App\Filament\Ins\Resources\InstallmentContracts\Pages\ListInstallmentContracts;
use App\Filament\Ins\Resources\InstallmentContracts\Pages\ViewInstallmentContract;
use App\Filament\Ins\Resources\InstallmentContracts\Schemas\InstallmentContractForm;
use App\Filament\Ins\Resources\InstallmentContracts\Schemas\InstallmentContractInfolist;
use App\Filament\Ins\Resources\InstallmentContracts\Tables\InstallmentContractsTable;
use App\Models\InstallmentContract;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class InstallmentContractResource extends Resource
{
    protected static ?string $model = InstallmentContract::class;

    protected static ?string $navigationLabel = 'عقود';

    protected static ?string $modelLabel = 'عقد تقسيط';

    protected static ?string $pluralModelLabel = 'عقود التقسيط';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->can('ادخال عقود') || $user?->is_prog;
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return InstallmentContractForm::configure($schema);
    }

    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();

        return $user?->can('تعديل عقود') || $user?->is_prog;
    }

    public static function canDelete(Model $record): bool
    {
        $user = Auth::user();

        return $user?->can('الغاء عقود') || $user?->is_prog;
    }

    public static function infolist(Schema $schema): Schema
    {
        return InstallmentContractInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstallmentContractsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstallmentContracts::route('/'),
            'view' => ViewInstallmentContract::route('/{record}'),
            'edit' => EditInstallmentContractRecord::route('/{record}/edit'),
            'edit-linked' => EditInstallmentContract::route('/{record}/edit-linked'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
