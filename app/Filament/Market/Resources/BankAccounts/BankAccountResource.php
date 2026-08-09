<?php

namespace App\Filament\Market\Resources\BankAccounts;

use App\Filament\Market\Resources\BankAccounts\Pages\CreateBankAccount;
use App\Filament\Market\Resources\BankAccounts\Pages\EditBankAccount;
use App\Filament\Market\Resources\BankAccounts\Pages\ListBankAccounts;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\BankAccount;
use App\Models\CustomerReceipt;
use App\Models\Expense;
use App\Models\FundTransfer;
use App\Models\SalaryTransaction;
use App\Models\SupplierPayment;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static ?string $navigationLabel = 'حسابات مصرفية';

    protected static ?string $modelLabel = 'حساب مصرفي';

    protected static ?string $pluralModelLabel = 'حسابات مصرفية';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::BanksAndCashBoxes;

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('ادخال مصارف');
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
                    ->label('اسم المصرف')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('account_number')
                    ->label('رقم الحساب')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('opening_balance')
                    ->label('رصيد بداية المدة')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_active')
                    ->label('نشط')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('اسم المصرف')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('account_number')
                    ->label('رقم الحساب')
                    ->searchable(),
                TextColumn::make('opening_balance')
                    ->label('الرصيد الافتتاحي')
                    ->numeric(3),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (): bool => Auth::user()?->is_prog || Auth::user()?->can('الغاء مصارف'))
                    ->hidden(fn (BankAccount $record): bool => CustomerReceipt::query()->where('bank_account_id', $record->id)->exists()
                        || SupplierPayment::query()->where('bank_account_id', $record->id)->exists()
                        || Expense::query()->where('bank_account_id', $record->id)->exists()
                        || SalaryTransaction::query()->where('bank_account_id', $record->id)->exists()
                        || FundTransfer::query()->where('from_bank_account_id', $record->id)->orWhere('to_bank_account_id', $record->id)->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankAccounts::route('/'),
            'create' => CreateBankAccount::route('/create'),
            'edit' => EditBankAccount::route('/{record}/edit'),
        ];
    }
}
