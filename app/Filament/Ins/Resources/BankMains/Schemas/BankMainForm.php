<?php

namespace App\Filament\Ins\Resources\BankMains\Schemas;

use App\Enums\BankCommissionType;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankMainForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات المصرف الأم')->schema([
                TextInput::make('name')
                    ->label('اسم المصرف')
                    ->required()
                    ->maxLength(255),
                Radio::make('r_type')
                    ->label('نوع العمولة')
                    ->options(BankCommissionType::class)
                    ->inline()
                    ->inlineLabel(false)
                    ->required()
                    ->live(),
                TextInput::make('ratio')
                    ->label(function ($get): string {
                        $type = $get('r_type');

                        if ($type instanceof BankCommissionType) {
                            return $type->ratioLabel();
                        }

                        return BankCommissionType::tryFrom((int) $type)?->ratioLabel() ?? 'القيمة';
                    })
                    ->numeric()
                    ->required()
                    ->default(0),
            ])->columns(1),
        ]);
    }
}
