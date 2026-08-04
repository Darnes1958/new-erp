<?php

namespace App\Filament\Admin\Resources\OurCompanies;

use App\Filament\Admin\Resources\OurCompanies\Pages\CreateOurCompany;
use App\Filament\Admin\Resources\OurCompanies\Pages\EditOurCompany;
use App\Filament\Admin\Resources\OurCompanies\Pages\ListOurCompanies;
use App\Filament\Admin\Resources\OurCompanies\Schemas\OurCompanyForm;
use App\Filament\Admin\Resources\OurCompanies\Tables\OurCompaniesTable;
use App\Models\OurCompany;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OurCompanyResource extends Resource
{
    protected static ?string $model = OurCompany::class;

    protected static ?string $navigationLabel = 'بيانات الشركات';

    protected static ?string $modelLabel = 'شركة';

    protected static ?string $pluralModelLabel = 'بيانات الشركات';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'إعدادات النظام';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && ($user->is_prog || $user->hasRole('admin'));
    }

    public static function canCreate(): bool
    {
        return (bool) Auth::user()?->is_prog;
    }

    public static function canDelete($record): bool
    {
        return (bool) Auth::user()?->is_prog;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        if ($user && ! $user->is_prog && filled($user->company)) {
            $query->where('connection_name', $user->company);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return OurCompanyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OurCompaniesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOurCompanies::route('/'),
            'create' => CreateOurCompany::route('/create'),
            'edit' => EditOurCompany::route('/{record}/edit'),
        ];
    }
}
