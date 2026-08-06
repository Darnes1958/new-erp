<?php

namespace App\Filament\Market\Pages\InpWarehouseTransfer\Schemas;

use App\Filament\Market\Tables\SalesItemPickerTable;
use App\Models\WarehouseTransferLine;
use App\Support\SalesItemAvailability;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class TransferLineForm
{
    public static function configure(Schema $schema, object $page): Schema
    {
        return $schema
            ->model(WarehouseTransferLine::class)
            ->statePath('lineData')
            ->columns(2)
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('barcode')
                            ->label('الباركود')
                            ->autocomplete(false)
                            ->dehydrated(false)
                            ->id('barcode')
                            ->extraInputAttributes([
                                'wire:keydown.enter' => 'checkBarcode($event.target.value)',
                            ])
                            ->columnSpan(2),
                        Select::make('item_id')
                            ->label('الصنف')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(onBlur: true)
                            ->relationship(
                                'item',
                                'name',
                                modifyQueryUsing: fn (Builder $query) => SalesItemAvailability::applyWarehouseStockFilter(
                                    $query,
                                    $page->warehouseFromId,
                                ),
                            )
                            ->afterStateUpdated(fn ($state) => $page->checkItem($state))
                            ->suffixAction(
                                Action::make('select_item')
                                    ->label('بحث عن الصنف')
                                    ->icon(Heroicon::MagnifyingGlass)
                                    ->schema([
                                        \Filament\Forms\Components\TableSelect::make('item_id')
                                            ->hiddenLabel()
                                            ->tableConfiguration(SalesItemPickerTable::class)
                                            ->tableArguments(fn (): array => [
                                                'warehouse_id' => $page->warehouseFromId,
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->fillForm(fn (): array => [
                                        'item_id' => $page->lineData['item_id'] ?? null,
                                    ])
                                    ->action(function (array $data) use ($page): void {
                                        $page->lineForm->fill(array_merge($page->lineData, [
                                            'item_id' => $data['item_id'],
                                        ]));
                                        $page->checkItem((int) $data['item_id']);
                                    }),
                            )
                            ->id('item_id')
                            ->columnSpan(2),
                        TextInput::make('qty_primary')
                            ->label('الكمية')
                            ->numeric()
                            ->required()
                            ->gt(0)
                            ->id('quantity')
                            ->extraInputAttributes([
                                'wire:keydown.enter' => 'checkQuantity($event.target.value)',
                            ]),
                        TextInput::make('stock_display')
                            ->label(function () use ($page): HtmlString|string {
                                if ($page->warehouseFromName) {
                                    return new HtmlString(
                                        '<span class="text-indigo-700">رصيد : </span>'
                                        .'<span class="text-primary-600">'.e($page->warehouseFromName).'</span>',
                                    );
                                }

                                return '-';
                            })
                            ->readOnly()
                            ->numeric()
                            ->dehydrated(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
