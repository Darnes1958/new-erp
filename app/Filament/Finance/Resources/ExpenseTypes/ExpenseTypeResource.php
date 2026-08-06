<?php

namespace App\Filament\Finance\Resources\ExpenseTypes;

use App\Filament\Finance\Resources\ExpenseTypes\Pages\CreateExpenseType;
use App\Filament\Finance\Resources\ExpenseTypes\Pages\EditExpenseType;
use App\Filament\Finance\Resources\ExpenseTypes\Pages\ListExpenseTypes;
use App\Filament\Finance\Support\FinanceNavigationGroup;
use App\Models\ExpenseType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ExpenseTypeResource extends Resource
{
    protected static ?string $model = ExpenseType::class;

    protected static ?string $navigationLabel = 'أنواع المصروفات';

    protected static ?string $modelLabel = 'نوع مصروف';

    protected static ?string $pluralModelLabel = 'أنواع المصروفات';

    protected static string|\UnitEnum|null $navigationGroup = FinanceNavigationGroup::Expenses;

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('ادخال مصروفات');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('البيان')
                ->required()
                ->unique(ignoreRecord: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('البيان')
                    ->searchable()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (ExpenseType $record): bool => $record->expenses()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenseTypes::route('/'),
            'create' => CreateExpenseType::route('/create'),
            'edit' => EditExpenseType::route('/{record}/edit'),
        ];
    }
}
