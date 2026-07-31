<?php

namespace App\Filament\Market\Resources\PurchaseInvoices\Schemas;

use App\Models\Item;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class PurchaseInvoiceForm
{
    public static function configure(Schema $schema): Schema
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
                    ->schema(self::lineSchema())
                    ->columns(5)
                    ->defaultItems(1)
                    ->addActionLabel('إضافة صنف')
                    ->columnSpanFull(),
            ]),
        ]);
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
                    self::updateLineTotal($set, $get);
                }),
            TextInput::make('qty_primary')
                ->label('الكمية')
                ->numeric()
                ->default(0)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, Get $get) => self::updateLineTotal($set, $get)),
            TextInput::make('qty_secondary')
                ->label('كمية 2')
                ->numeric()
                ->default(0)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, Get $get) => self::updateLineTotal($set, $get)),
            TextInput::make('unit_cost_primary')
                ->label('السعر')
                ->numeric()
                ->default(0)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, Get $get) => self::updateLineTotal($set, $get)),
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

    protected static function updateLineTotal(Set $set, Get $get): void
    {
        $total = ((float) $get('qty_primary') * (float) $get('unit_cost_primary'))
            + ((float) $get('qty_secondary') * (float) $get('unit_cost_primary'));

        $set('line_cost_total', round($total, 3));
    }
}
