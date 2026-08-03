<?php

namespace App\Filament\Ins\Resources\WrongDeductions;

use App\Filament\Ins\Resources\WrongDeductions\Pages\ListWrongDeductions;
use App\Filament\Ins\Resources\WrongDeductions\Tables\WrongDeductionsTable;
use App\Models\WrongDeduction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class WrongDeductionResource extends Resource
{
    protected static ?string $model = WrongDeduction::class;

    protected static ?string $navigationLabel = 'أقساط واردة بالخطأ';

    protected static ?string $modelLabel = 'قسط بالخطأ';

    protected static ?string $pluralModelLabel = 'أقساط بالخطأ';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|\UnitEnum|null $navigationGroup = 'خصومات ومدفوعات';

    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        return (string) WrongDeduction::query()->count();
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
        return WrongDeductionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWrongDeductions::route('/'),
        ];
    }
}
