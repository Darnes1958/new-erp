<?php

namespace App\Filament\Ins\Resources\InstallmentBanks\Schemas;

use App\Filament\Ins\Resources\PayrollBanks\Schemas\PayrollBankForm;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstallmentBankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات فرع المصرف')->schema(self::fields())->columns(1),
        ]);
    }

    /**
     * @return array<int, Select|TextInput>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('name')
                ->label('اسم المصرف')
                ->required()
                ->maxLength(255),
            Select::make('payroll_bank_id')
                ->label('المصرف التجميعي')
                ->relationship('payrollBank', 'name')
                ->searchable()
                ->preload()
                ->createOptionForm([
                    Section::make('ادخال حساب تجميعي')
                        ->schema(PayrollBankForm::fields()),
                ])
                ->required(),
        ];
    }
}
