<?php

namespace App\Filament\Ins\Resources\PayrollBanks\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayrollBankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الحساب التجميعي')->schema(self::fields())->columns(1),
        ]);
    }

    /**
     * @return array<int, Select|TextInput>
     */
    public static function fields(): array
    {
        return [
            Select::make('bank_main_id')
                ->label('المصرف الأم')
                ->relationship('bankMain', 'name')
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('name')
                ->label('اسم المصرف')
                ->required()
                ->maxLength(255),
            TextInput::make('account_number')
                ->label('رقم الحساب')
                ->required()
                ->maxLength(255),
        ];
    }
}
