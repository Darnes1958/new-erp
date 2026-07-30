<?php

namespace App\Filament\Market\Resources\Items;

use App\Filament\Market\Resources\Items\Pages\CreateItem;
use App\Filament\Market\Resources\Items\Pages\EditItem;
use App\Filament\Market\Resources\Items\Pages\ListItems;
use App\Models\Item;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static ?string $navigationLabel = 'أصناف';

    protected static ?string $modelLabel = 'صنف';

    protected static ?string $pluralModelLabel = 'أصناف';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = 'مخازن وأصناف';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('barcode')
                    ->label('الباركود')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('itemType.name')
                    ->label('التصنيف')
                    ->sortable(),
                TextColumn::make('brand.name')
                    ->label('الشركة')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('primaryUnit.name')
                    ->label('الوحدة')
                    ->toggleable(),
                IconColumn::make('has_dual_unit')
                    ->label('وحدتان')
                    ->boolean(),
                TextColumn::make('default_buy_price')
                    ->label('سعر الشراء')
                    ->numeric(decimalPlaces: 3),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->striped()
            ->filters([
                SelectFilter::make('item_type_id')
                    ->label('التصنيف')
                    ->relationship('itemType', 'name'),
                SelectFilter::make('brand_id')
                    ->label('الشركة')
                    ->relationship('brand', 'name'),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItems::route('/'),
            'create' => CreateItem::route('/create'),
            'edit' => EditItem::route('/{record}/edit'),
        ];
    }
}
