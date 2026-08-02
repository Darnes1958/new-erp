<?php

namespace App\Filament\Market\Pages\InpSellOffer\Schemas;

use App\Filament\Market\Tables\ItemPickerTable;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TableSelect;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class SalesOfferLineForm
{
    public static function configure(Schema $schema, object $page): Schema
    {
        return $schema
            ->model(\App\Models\SalesOfferInvoiceLineWork::class)
            ->statePath('lineData')
            ->columns(1)
            ->components([
                TextInput::make('barcode')
                    ->hiddenLabel()
                    ->prefix('الباركود')
                    ->columnSpanFull()
                    ->autocomplete(false)
                    ->extraInputAttributes([
                        'wire:keydown.enter' => 'checkBarcode($event.target.value)',
                    ])
                    ->id('barcode'),
                Select::make('item_id')
                    ->hiddenLabel()
                    ->prefix('الصنف')
                    ->columnSpanFull()
                    ->searchable()
                    ->preload()
                    ->relationship('item', 'name')
                    ->live()
                    ->required()
                    ->afterStateUpdated(fn ($state) => $page->checkItem($state))
                    ->suffixAction(
                        Action::make('select_item')
                            ->hiddenLabel()
                            ->icon(Heroicon::MagnifyingGlass)
                            ->schema([
                                TableSelect::make('item_id')
                                    ->hiddenLabel()
                                    ->relationship('item', 'name')
                                    ->tableConfiguration(ItemPickerTable::class)
                                    ->columnSpanFull(),
                            ])
                            ->fillForm(fn (): array => [
                                'item_id' => $page->lineData['item_id'] ?? null,
                            ])
                            ->action(function (array $data) use ($page): void {
                                $page->checkItem((int) $data['item_id']);
                            })
                    )
                    ->id('item_id'),
                TextInput::make('stock_display')
                    ->hiddenLabel()
                    ->prefix('الرصيد')
                    ->columnSpanFull()
                    ->readOnly()
                    ->dehydrated(false)
                    ->visible(fn () => filled($page->lineData['item_id'] ?? null)),
                TextInput::make('unit_price_primary')
                    ->hiddenLabel()
                    ->prefix('السعر')
                    ->prefixIcon('heroicon-m-currency-dollar')
                    ->prefixIconColor('info')
                    ->columnSpanFull()
                    ->numeric()
                    ->live()
                    ->required()
                    ->gt(0)
                    ->readOnly(fn (): bool => ! $page->canEditSellPrice())
                    ->validationMessages([
                        'gt' => 'سعر البيع لا يجوز أن يكون صفر',
                    ])
                    ->extraAttributes(['wire:keydown.enter.prevent' => 'focusQuantity'])
                    ->id('unit_price_primary'),
                TextInput::make('qty_primary')
                    ->hiddenLabel()
                    ->prefix('الكمية')
                    ->prefixIcon('heroicon-m-shopping-cart')
                    ->prefixIconColor('warning')
                    ->columnSpanFull()
                    ->numeric()
                    ->required()
                    ->gt(0)
                    ->extraAttributes(['wire:keydown.enter.prevent' => 'addLine'])
                    ->id('qty_primary'),
                Hidden::make('sales_offer_invoice_work_id')->default(fn () => Auth::id()),
                Hidden::make('created_by')->default(fn () => Auth::id()),
            ]);
    }
}
