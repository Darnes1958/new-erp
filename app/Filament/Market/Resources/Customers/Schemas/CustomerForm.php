<?php

namespace App\Filament\Market\Resources\Customers\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الزبون')->schema([
                TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(255),
                Select::make('customer_type_id')
                    ->label('التصنيف')
                    ->relationship('customerType', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255),
                    ]),
                TextInput::make('address')
                    ->label('العنوان')
                    ->maxLength(255),
                TextInput::make('mdar')
                    ->label('مدار')
                    ->maxLength(255),
                TextInput::make('libyana')
                    ->label('لبيانا')
                    ->maxLength(255),
                TextInput::make('card_no')
                    ->label('رقم البطاقة')
                    ->maxLength(255),
                Hidden::make('created_by')
                    ->default(fn () => Auth::id()),
            ])->columns(2),
        ]);
    }
}
