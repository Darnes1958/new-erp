<?php

namespace App\Filament\Admin\Resources\BankExcelImportSettings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankExcelImportSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('إعدادات المصرف')->schema([
                TextInput::make('name')
                    ->label('اسم المصرف / الوصف')
                    ->required()
                    ->maxLength(255),
                Select::make('payroll_bank_id')
                    ->label('الحساب التجميعي')
                    ->relationship('payrollBank', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('heading_row')
                    ->label('رقم سطر العنوان')
                    ->numeric()
                    ->default(1)
                    ->required()
                    ->minValue(1),
            ])->columns(2),
            Section::make('أسماء أعمدة Excel')->schema([
                TextInput::make('column_amount')
                    ->label('عمود قيمة الخصم')
                    ->required()
                    ->maxLength(255)
                    ->helperText('النص الظاهر في رأس العمود بملف Excel'),
                TextInput::make('column_deduction_date')
                    ->label('عمود تاريخ الخصم')
                    ->required()
                    ->maxLength(255),
                TextInput::make('column_customer_name')
                    ->label('عمود اسم العميل')
                    ->required()
                    ->maxLength(255),
                TextInput::make('column_account_number')
                    ->label('عمود رقم الحساب')
                    ->required()
                    ->maxLength(255),
            ])->columns(2),
        ]);
    }
}
