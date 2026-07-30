<?php

namespace App\Filament\Market\Resources\SalesInvoices;

use App\Filament\Market\Resources\SalesInvoices\Pages\CreateSalesInvoice;
use App\Filament\Market\Resources\SalesInvoices\Pages\EditSalesInvoice;
use App\Filament\Market\Resources\SalesInvoices\Pages\ListSalesInvoices;
use App\Filament\Market\Resources\SalesInvoices\Pages\ViewSalesInvoice;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\SalesInvoice;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SalesInvoiceResource extends Resource
{
    protected static ?string $model = SalesInvoice::class;

    protected static ?string $navigationLabel = 'فواتير البيع';

    protected static ?string $modelLabel = 'فاتورة بيع';

    protected static ?string $pluralModelLabel = 'فواتير البيع';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'فواتير مبيعات';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الفاتورة')->schema([
                DatePicker::make('invoice_date')
                    ->label('التاريخ')
                    ->required()
                    ->default(now()),
                Select::make('customer_id')
                    ->label('الزبون')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('payment_method_id')
                    ->label('طريقة الدفع')
                    ->relationship('paymentMethod', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Select::make('warehouse_id')
                    ->label('المخزن')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Toggle::make('is_retail')
                    ->label('بيع مفرد')
                    ->default(true),
                TextInput::make('extra_cost')
                    ->label('تكاليف إضافية')
                    ->numeric()
                    ->default(0)
                    ->live(onBlur: true),
                TextInput::make('discount')
                    ->label('خصم')
                    ->numeric()
                    ->default(0)
                    ->live(onBlur: true),
                TextInput::make('difference_amount')
                    ->label('فرق عملة')
                    ->numeric()
                    ->default(0)
                    ->live(onBlur: true),
                TextInput::make('amount_paid')
                    ->label('المدفوع')
                    ->numeric()
                    ->default(0),
                TextInput::make('lines_subtotal')
                    ->label('إجمالي البنود')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('grand_total')
                    ->label('الإجمالي النهائي')
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
                    ->columns(6)
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
                TextEntry::make('customer.name')->label('الزبون'),
                TextEntry::make('paymentMethod.name')->label('طريقة الدفع'),
                TextEntry::make('warehouse.name')->label('المخزن'),
                TextEntry::make('is_retail')->label('بيع مفرد')->formatStateUsing(fn ($state) => $state ? 'نعم' : 'لا'),
                TextEntry::make('lines_subtotal')->label('إجمالي البنود')->numeric(decimalPlaces: 3),
                TextEntry::make('extra_cost')->label('تكاليف إضافية')->numeric(decimalPlaces: 3),
                TextEntry::make('discount')->label('خصم')->numeric(decimalPlaces: 3),
                TextEntry::make('difference_amount')->label('فرق عملة')->numeric(decimalPlaces: 3),
                TextEntry::make('grand_total')->label('الإجمالي النهائي')->numeric(decimalPlaces: 3),
                TextEntry::make('amount_paid')->label('المدفوع')->numeric(decimalPlaces: 3),
                TextEntry::make('balance')->label('الباقي')->numeric(decimalPlaces: 3),
                TextEntry::make('notes')->label('ملاحظات')->columnSpanFull(),
            ])->columns(3),

            Section::make('البنود')->schema([
                RepeatableEntry::make('lines')
                    ->label('')
                    ->schema([
                        TextEntry::make('item.name')->label('الصنف'),
                        TextEntry::make('qty_primary')->label('كمية 1')->numeric(decimalPlaces: 3),
                        TextEntry::make('qty_secondary')->label('كمية 2')->numeric(decimalPlaces: 3),
                        TextEntry::make('unit_price_primary')->label('سعر 1')->numeric(decimalPlaces: 3),
                        TextEntry::make('unit_price_secondary')->label('سعر 2')->numeric(decimalPlaces: 3),
                        TextEntry::make('line_total')->label('الإجمالي')->numeric(decimalPlaces: 3),
                        TextEntry::make('profit')->label('الربح')->numeric(decimalPlaces: 3),
                    ])
                    ->columns(7)
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
                TextColumn::make('customer.name')
                    ->label('الزبون')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('invoice_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('lines_subtotal')
                    ->label('إجمالي البنود')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->label('الإجمالي')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->label('المدفوع')
                    ->numeric(decimalPlaces: 3),
                TextColumn::make('balance')
                    ->label('الباقي')
                    ->numeric(decimalPlaces: 3),
                IconColumn::make('is_retail')
                    ->label('مفرد')
                    ->boolean(),
                TextColumn::make('warehouse.name')
                    ->label('المخزن')
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->filters([
                SelectFilter::make('customer_id')
                    ->label('الزبون')
                    ->relationship('customer', 'name'),
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
            'index' => ListSalesInvoices::route('/'),
            'create' => CreateSalesInvoice::route('/create'),
            'view' => ViewSalesInvoice::route('/{record}'),
            'edit' => EditSalesInvoice::route('/{record}/edit'),
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

                    $paymentMethodId = $get('../../payment_method_id');
                    if ($paymentMethodId) {
                        $price = ItemPrice::query()
                            ->where('item_id', $state)
                            ->where('payment_method_id', $paymentMethodId)
                            ->where('price_kind', 'sell')
                            ->first();

                        $set('unit_price_primary', $price?->price_primary ?? 0);
                        $set('unit_price_secondary', $price?->price_secondary ?? 0);
                    }

                    static::updateSalesLineTotal($set, $get);
                }),
            TextInput::make('qty_primary')
                ->label('كمية 1')
                ->numeric()
                ->default(0)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, Get $get) => static::updateSalesLineTotal($set, $get)),
            TextInput::make('qty_secondary')
                ->label('كمية 2')
                ->numeric()
                ->default(0)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, Get $get) => static::updateSalesLineTotal($set, $get)),
            TextInput::make('unit_price_primary')
                ->label('سعر 1')
                ->numeric()
                ->default(0)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, Get $get) => static::updateSalesLineTotal($set, $get)),
            TextInput::make('unit_price_secondary')
                ->label('سعر 2')
                ->numeric()
                ->default(0)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, Get $get) => static::updateSalesLineTotal($set, $get)),
            TextInput::make('line_total')
                ->label('الإجمالي')
                ->numeric()
                ->disabled()
                ->dehydrated(),
            Hidden::make('barcode'),
            Hidden::make('created_by')
                ->default(fn () => Auth::id()),
        ];
    }

    protected static function updateSalesLineTotal(Set $set, Get $get): void
    {
        $total = ((float) $get('qty_primary') * (float) $get('unit_price_primary'))
            + ((float) $get('qty_secondary') * (float) $get('unit_price_secondary'));

        $set('line_total', round($total, 3));
    }
}
