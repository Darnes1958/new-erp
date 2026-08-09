<?php

namespace App\Filament\Market\Resources\InventoryCountLines\Schemas;

use App\Models\InventoryCountLine;
use App\Models\InventoryCountSession;
use App\Models\Item;
use App\Services\Inventory\SalesInventoryService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class InventoryCountLineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    Hidden::make('inventory_count_session_id')
                        ->default(fn (): ?int => InventoryCountSession::activeSessionId()),
                    Select::make('warehouse_id')
                        ->label('المكان')
                        ->relationship('warehouse', 'name')
                        ->preload()
                        ->searchable()
                        ->required()
                        ->live(),
                    Select::make('item_id')
                        ->label('الصنف')
                        ->options(fn (Get $get): Collection => Item::query()
                            ->join('warehouse_stocks', 'items.id', '=', 'warehouse_stocks.item_id')
                            ->where('warehouse_stocks.warehouse_id', $get('warehouse_id'))
                            ->whereNotIn('items.id', InventoryCountLine::query()
                                ->where('inventory_count_session_id', $get('inventory_count_session_id') ?? InventoryCountSession::activeSessionId())
                                ->where('warehouse_id', $get('warehouse_id'))
                                ->pluck('item_id'))
                            ->orderBy('items.name')
                            ->pluck('items.name', 'items.id'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->disabled(fn (Get $get): bool => blank($get('warehouse_id'))),
                    TextInput::make('actual_balance')
                        ->label('الرصيد الفعلي')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->disabled(fn (Get $get): bool => blank($get('item_id')))
                        ->helperText(function (Get $get): ?string {
                            if (blank($get('warehouse_id')) || blank($get('item_id'))) {
                                return null;
                            }

                            $bookBalance = app(SalesInventoryService::class)->warehouseStockQty(
                                (int) $get('item_id'),
                                (int) $get('warehouse_id'),
                            );

                            return 'الرصيد الدفتري: '.number_format($bookBalance, 3, '.', ',');
                        })
                        ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                            if (blank($get('item_id')) || blank($get('warehouse_id')) || $state === null) {
                                return;
                            }

                            $bookBalance = app(SalesInventoryService::class)->warehouseStockQty(
                                (int) $get('item_id'),
                                (int) $get('warehouse_id'),
                            );
                            $difference = (float) $state - $bookBalance;
                            $buyPrice = (float) Item::query()->whereKey($get('item_id'))->value('default_buy_price');

                            $set('book_balance', $bookBalance);
                            $set('quantity_difference', $difference);
                            $set('value_amount', round($difference * $buyPrice, 3));
                        }),
                    Hidden::make('book_balance'),
                    Hidden::make('quantity_difference'),
                    Hidden::make('value_amount'),
                    Hidden::make('created_by')
                        ->default(fn (): ?int => Auth::id()),
                ])
                ->columns(1),
        ]);
    }
}
