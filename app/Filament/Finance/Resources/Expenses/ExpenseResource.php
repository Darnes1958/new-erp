<?php

namespace App\Filament\Finance\Resources\Expenses;

use App\Filament\Finance\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Finance\Resources\Expenses\Pages\EditExpense;
use App\Filament\Finance\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Finance\Resources\Expenses\Schemas\ExpenseForm;
use App\Filament\Finance\Resources\Expenses\Tables\ExpensesTable;
use App\Filament\Finance\Support\FinanceNavigationGroup;
use App\Models\Expense;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationLabel = 'مصروفات';

    protected static ?string $modelLabel = 'مصروف';

    protected static ?string $pluralModelLabel = 'مصروفات';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = FinanceNavigationGroup::Expenses;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('ادخال مصروفات');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user?->is_prog
            || $user?->can('ادخال مصروفات')
            || $user?->can('الغاء مصروفات');
    }

    public static function form(Schema $schema): Schema
    {
        return ExpenseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpensesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenses::route('/'),
            'create' => CreateExpense::route('/create'),
            'edit' => EditExpense::route('/{record}/edit'),
        ];
    }
}
