<?php

namespace App\Filament\Market\Resources\PurchaseInvoices;

use App\Filament\Market\Resources\PurchaseInvoices\Pages\CreatePurchaseInvoice;
use App\Filament\Market\Resources\PurchaseInvoices\Pages\EditPurchaseInvoice;
use App\Filament\Market\Resources\PurchaseInvoices\Pages\ListPurchaseInvoices;
use App\Filament\Market\Resources\PurchaseInvoices\Pages\ViewPurchaseInvoice;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PurchaseInvoiceResource extends Resource
{
    protected static ?string $model = PurchaseInvoice::class;

    protected static ?string $navigationLabel = 'فواتير الشراء';

    protected static ?string $modelLabel = 'فاتورة شراء';

    protected static ?string $pluralModelLabel = 'فواتير الشراء';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string|\UnitEnum|null $navigationGroup = 'فواتير شراء';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الفاتورة')->schema([
                DatePicker::make('invoice_date')
                    ->label('التاريخ')
                    ->required()
                    ->default(now()),
                Select::make('supplier_id')
                    ->label('المورد')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('payment_method_id')
                    ->label('طريقة الدفع')
                    ->relationship('paymentMethod', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('warehouse_id')
                    ->label('المخزن')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('amount_paid')
                    ->label('المدفوع')
                    ->numeric()
                    ->default(0),
                TextInput::make('lines_subtotal')
                    ->label('إجمالي البنود')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('balance')
                    ->label('الباقي')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),
                Hidden::make('created_by')
                    ->default(fn () => Auth::id()),
            ])->columns(3),

            Section::make('بنود الفاتورة')->schema([
                Repeater::make('lines')
                    ->label('البنود')
                    ->relationship()
                    ->schema(static::lineSchema())
                    ->columns(5)
                    ->defaultItems(1)
                    ->addActionLabel('إضافة صنف')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الفاتورة')->schema([
                TextEntry::make('id')->label('الرقم'),
                TextEntry::make('invoice_date')->label('التاريخ')->date(),
                TextEntry::make('supplier.name')->label('المورد'),
                TextEntry::make('paymentMethod.name')->label('طريقة الدفع'),
                TextEntry::make('warehouse.name')->label('المخزن'),
                TextEntry::make('lines_subtotal')->label('إجمالي البنود')->numeric(decimalPlaces: 3),
                TextEntry::make('amount_paid')->label('المدفوع')->numeric(decimalPlaces: 3),
                TextEntry::make('balance')->label('الباقي')->numeric(decimalPlaces: 3),
                TextEntry::make('notes')->label('ملاحظات')->columnSpanFull(),
            ])->columns(3),

            Section::make('البنود')->schema([
                RepeatableEntry::make('lines')
                    ->label('')
                    ->schema([
                        TextEntry::make('item.name')->label('الصنف'),
                        TextEntry::make('qty_primary')->label('الكمية 1')->numeric(decimalPlaces: 3),
                        TextEntry::make('qty_secondary')->label('الكمية 2')->numeric(decimalPlaces: 3),
                        TextEntry::make('unit_cost_primary')->label('سعر الوحدة')->numeric(decimalPlaces: 3),
                        TextEntry::make('line_cost_total')->label('الإجمالي')->numeric(decimalPlaces: 3),
                    ])
                    ->columns(5)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('supplier.name')
                    ->label('المورد')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('invoice_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('lines_subtotal')
                    ->label('الإجمالي')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->label('المدفوع')
                    ->numeric(decimalPlaces: 3),
                TextColumn::make('balance')
                    ->label('الباقي')
                    ->numeric(decimalPlaces: 3),
                TextColumn::make('warehouse.name')
                    ->label('المخزن')
                    ->toggleable(),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('المورد')
                    ->relationship('supplier', 'name'),
                SelectFilter::make('warehouse_id')
                    ->label('المخزن')
                    ->relationship('warehouse', 'name'),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseInvoices::route('/'),
            'create' => CreatePurchaseInvoice::route('/create'),
            'view' => ViewPurchaseInvoice::route('/{record}'),
            'edit' => EditPurchaseInvoice::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected static function lineSchema(): array
    {
        return [
            Select::make('item_id')
                ->label('الصنف')
                ->relationship('item', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                    if (! $state) {
                        return;
                    }

                    $item = Item::query()->find($state);
                    $set('barcode', $item?->barcode);
                    $set('unit_cost_primary', $item?->default_buy_price ?? 0);
                    static::updatePurchaseLineTotal($set, $get);
                }),
            TextInput::make('qty_primary')
                ->label('كمية 1')
                ->numeric()
                ->default(0)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, Get $get) => static::updatePurchaseLineTotal($set, $get)),
            TextInput::make('qty_secondary')
                ->label('كمية 2')
                ->numeric()
                ->default(0)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, Get $get) => static::updatePurchaseLineTotal($set, $get)),
            TextInput::make('unit_cost_primary')
                ->label('سعر الوحدة')
                ->numeric()
                ->default(0)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, Get $get) => static::updatePurchaseLineTotal($set, $get)),
            TextInput::make('line_cost_total')
                ->label('الإجمالي')
                ->numeric()
                ->disabled()
                ->dehydrated(),
            Hidden::make('barcode'),
            Hidden::make('remaining_qty_primary')
                ->dehydrateStateUsing(fn ($state, Get $get) => $state ?? $get('qty_primary')),
            Hidden::make('remaining_qty_secondary')
                ->dehydrateStateUsing(fn ($state, Get $get) => $state ?? $get('qty_secondary')),
            Hidden::make('created_by')
                ->default(fn () => Auth::id()),
        ];
    }

    protected static function updatePurchaseLineTotal(Set $set, Get $get): void
    {
        $total = ((float) $get('qty_primary') * (float) $get('unit_cost_primary'))
            + ((float) $get('qty_secondary') * (float) $get('unit_cost_primary'));

        $set('line_cost_total', round($total, 3));
    }
}
