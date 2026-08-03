<?php

namespace App\Filament\Ins\Resources\InstallmentStopsWithoutContract\Schemas;

use App\Models\PayrollBank;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstallmentStopWithoutContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('payroll_bank_id')
                            ->label('المصرف التجميعي')
                            ->options(fn (): array => PayrollBank::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('account_number')
                            ->label('رقم الحساب')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('stop_date')
                            ->label('تاريخ الإيقاف')
                            ->default(now())
                            ->required(),
                    ]),
            ]);
    }
}
