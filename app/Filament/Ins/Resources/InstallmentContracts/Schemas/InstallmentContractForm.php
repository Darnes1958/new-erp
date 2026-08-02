<?php

namespace App\Filament\Ins\Resources\InstallmentContracts\Schemas;

use App\Filament\Ins\Concerns\RecalculatesInstallmentAmount;
use App\Filament\Ins\Support\InstallmentContractFieldAttributes;
use App\Models\Customer;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class InstallmentContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id')
                    ->label('رقم العقد')
                    ->required()
                    ->numeric()
                    ->unique(ignoreRecord: true)
                    ->unique(table: InstallmentContractArchive::class, column: 'id')
                    ->autofocus(),
                Select::make('customer_id')
                    ->label('الزبون')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?int $state, Set $set): void {
                        if (! $state) {
                            return;
                        }

                        $previous = InstallmentContract::query()
                            ->where('customer_id', $state)
                            ->orderBy('contract_start')
                            ->first()
                            ?? InstallmentContractArchive::query()
                                ->where('customer_id', $state)
                                ->orderBy('contract_start')
                                ->first();

                        if (! $previous) {
                            return;
                        }

                        $set('installment_bank_id', $previous->installment_bank_id);
                        $set('bank_account_number', $previous->bank_account_number);
                        $set('payroll_bank_id', $previous->payroll_bank_id);
                    })
                    ->createOptionForm([
                        Section::make('ادخال زبون جديد')->schema([
                            TextInput::make('name')
                                ->required()
                                ->unique(Customer::class)
                                ->label('الاسم'),
                            TextInput::make('address')->label('العنوان'),
                            TextInput::make('phone')->label('الهاتف'),
                        ]),
                    ]),
                Select::make('installment_bank_id')
                    ->label('المصرف')
                    ->relationship('installmentBank', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?int $state, Set $set): void {
                        if (! $state) {
                            return;
                        }

                        $payrollBankId = \App\Models\InstallmentBank::query()
                            ->whereKey($state)
                            ->value('payroll_bank_id');

                        $set('payroll_bank_id', $payrollBankId);
                    }),
                TextInput::make('bank_account_number')
                    ->label('رقم الحساب')
                    ->required(),
                Select::make('workplace_id')
                    ->label('مكان العمل')
                    ->relationship('workplace', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('مكان العمل')
                            ->required(),
                    ]),
                DatePicker::make('contract_start')
                    ->label('تاريخ العقد')
                    ->required()
                    ->maxDate(now())
                    ->default(now()),
                TextInput::make('contract_total')
                    ->label('قيمة العقد')
                    ->numeric()
                    ->required()
                    ->live(onBlur: true)
                    ->id('contract_total')
                    ->afterStateUpdated(function (Get $get, Set $set): void {
                        RecalculatesInstallmentAmount::syncInstallmentAmount($get, $set);
                    }),
                TextInput::make('installment_count')
                    ->label('عدد الأقساط')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                        RecalculatesInstallmentAmount::syncInstallmentAmount($get, $set, $state);
                    })
                    ->extraInputAttributes(InstallmentContractFieldAttributes::installmentCountEnterKey())
                    ->id('installment_count'),
                TextInput::make('installment_amount')
                    ->label('القسط')
                    ->numeric()
                    ->required()
                    ->id('installment_amount'),
                TextInput::make('cheques_in')
                    ->label('عدد الصكوك المستلمة')
                    ->numeric()
                    ->default(0)
                    ->required(),
                TextInput::make('cheques_out')
                    ->label('عدد الصكوك المسلمة')
                    ->numeric()
                    ->default(0)
                    ->readOnly(),
                Hidden::make('payroll_bank_id'),
                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),
            ]);
    }
}
