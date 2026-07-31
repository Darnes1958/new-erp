<?php

namespace App\Filament\Market\Resources\Items\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الصنف')->schema([
                TextInput::make('name')
                    ->label('اسم الصنف')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->columnSpan(2),
                TextInput::make('barcode')
                    ->label('الباركود')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Select::make('item_type_id')
                    ->label('التصنيف')
                    ->relationship('itemType', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required(),
                    ]),
                Select::make('brand_id')
                    ->label('الشركة')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required(),
                    ]),
                Select::make('primary_unit_id')
                    ->label('الوحدة الكبرى')
                    ->relationship('primaryUnit', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required(),
                    ]),
                Toggle::make('has_dual_unit')
                    ->label('وحدتان')
                    ->live()
                    ->default(false),
                Select::make('secondary_unit_id')
                    ->label('الوحدة الصغرى')
                    ->relationship('secondaryUnit', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => (bool) $get('has_dual_unit'))
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required(),
                    ]),
                TextInput::make('conversion_factor')
                    ->label('معامل التحويل')
                    ->numeric()
                    ->default(1)
                    ->visible(fn (Get $get): bool => (bool) $get('has_dual_unit')),
                TextInput::make('default_buy_price')
                    ->label('سعر الشراء الافتراضي')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('نشط')
                    ->default(true),
            ])->columns(2),
        ]);
    }
}
