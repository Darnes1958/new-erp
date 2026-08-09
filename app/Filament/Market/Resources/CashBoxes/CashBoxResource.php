<?php

namespace App\Filament\Market\Resources\CashBoxes;

use App\Filament\Market\Resources\CashBoxes\Pages\CreateCashBox;
use App\Filament\Market\Resources\CashBoxes\Pages\EditCashBox;
use App\Filament\Market\Resources\CashBoxes\Pages\ListCashBoxes;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\CashBox;
use App\Models\CustomerReceipt;
use App\Models\Expense;
use App\Models\FundTransfer;
use App\Models\SalaryTransaction;
use App\Models\SupplierPayment;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CashBoxResource extends Resource
{
    protected static ?string $model = CashBox::class;

    protected static ?string $navigationLabel = 'خزائن';

    protected static ?string $modelLabel = 'خزينة';

    protected static ?string $pluralModelLabel = 'خزائن';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::BanksAndCashBoxes;

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('ادخال خزائن');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم الخزينة')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('opening_balance')
                    ->label('رصيد بداية المدة')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Select::make('assigned_user_id')
                    ->label('المستخدم')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
                Toggle::make('is_active')
                    ->label('نشط')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم الخزينة')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('opening_balance')
                    ->label('رصيد بداية المدة')
                    ->numeric(3),
                TextColumn::make('assignedUser.name')
                    ->label('المستخدم'),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (): bool => Auth::user()?->is_prog || Auth::user()?->can('الغاء خزينة'))
                    ->hidden(fn (CashBox $record): bool => CustomerReceipt::query()->where('cash_box_id', $record->id)->exists()
                        || SupplierPayment::query()->where('cash_box_id', $record->id)->exists()
                        || Expense::query()->where('cash_box_id', $record->id)->exists()
                        || SalaryTransaction::query()->where('cash_box_id', $record->id)->exists()
                        || FundTransfer::query()->where('from_cash_box_id', $record->id)->orWhere('to_cash_box_id', $record->id)->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashBoxes::route('/'),
            'create' => CreateCashBox::route('/create'),
            'edit' => EditCashBox::route('/{record}/edit'),
        ];
    }
}
