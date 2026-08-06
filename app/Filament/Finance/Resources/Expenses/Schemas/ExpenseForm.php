<?php

namespace App\Filament\Finance\Resources\Expenses\Schemas;

use App\Enums\FinancePaymentMethod;
use App\Models\ExpenseType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('expense_type_id')
                ->label('نوع المصروفات')
                ->relationship('expenseType', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->createOptionForm([
                    Section::make('ادخال نوع مصروفات جديد')->schema([
                        TextInput::make('name')
                            ->label('البيان')
                            ->required()
                            ->unique(ignoreRecord: true),
                    ]),
                ]),
            Radio::make('payment_method')
                ->label('طريقة الدفع')
                ->options(FinancePaymentMethod::class)
                ->default(FinancePaymentMethod::Cash->value)
                ->inline()
                ->live(),
            Select::make('bank_account_id')
                ->label('المصرف')
                ->relationship('bankAccount', 'name')
                ->searchable()
                ->preload()
                ->required(fn (Get $get): bool => self::isPaymentMethod($get, FinancePaymentMethod::Bank))
                ->visible(fn (Get $get): bool => self::isPaymentMethod($get, FinancePaymentMethod::Bank)),
            Select::make('cash_box_id')
                ->label('الخزينة')
                ->relationship('cashBox', 'name')
                ->searchable()
                ->preload()
                ->required(fn (Get $get): bool => self::isPaymentMethod($get, FinancePaymentMethod::Cash))
                ->visible(fn (Get $get): bool => self::isPaymentMethod($get, FinancePaymentMethod::Cash))
                ->createOptionForm([
                    Section::make('ادخال حساب خزينة جديد')->schema([
                        TextInput::make('name')
                            ->label('اسم الخزينة')
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('opening_balance')
                            ->label('رصيد بداية المدة')
                            ->numeric()
                            ->required(),
                    ]),
                ]),
            Select::make('warehouse_id')
                ->label('المكان')
                ->relationship('warehouse', 'name')
                ->searchable()
                ->preload()
                ->placeholder('غير محدد'),
            DatePicker::make('expense_date')
                ->label('التاريخ')
                ->required()
                ->default(now()),
            TextInput::make('amount')
                ->label('المبلغ')
                ->numeric()
                ->required(),
            TextInput::make('notes')
                ->label('ملاحظات'),
            Hidden::make('created_by')
                ->default(fn (): ?int => Auth::id()),
        ]);
    }

    private static function isPaymentMethod(Get $get, FinancePaymentMethod $method): bool
    {
        $value = $get('payment_method');

        if ($value instanceof FinancePaymentMethod) {
            return $value === $method;
        }

        return FinancePaymentMethod::tryFrom((int) $value) === $method;
    }
}
