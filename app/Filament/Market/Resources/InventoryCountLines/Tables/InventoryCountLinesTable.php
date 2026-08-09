<?php

namespace App\Filament\Market\Resources\InventoryCountLines\Tables;

use App\Models\InventoryCountLine;
use App\Services\Inventory\InventoryCountService;
use App\Services\Inventory\SalesInventoryService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use RuntimeException;
use Throwable;

class InventoryCountLinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['session', 'warehouse', 'item']))
            ->columns([
                TextColumn::make('session.title')
                    ->label('الجلسة'),
                TextColumn::make('warehouse.name')
                    ->label('المكان')
                    ->sortable(),
                TextColumn::make('item_id')
                    ->label('رقم الصنف')
                    ->sortable(),
                TextColumn::make('item.name')
                    ->label('اسم الصنف')
                    ->searchable(),
                TextColumn::make('book_balance')
                    ->label('الرصيد الدفتري')
                    ->numeric(3),
                TextColumn::make('actual_balance')
                    ->label('الرصيد الفعلي')
                    ->numeric(3),
                TextColumn::make('current_stock')
                    ->label('الرصيد الحالي')
                    ->numeric(3)
                    ->getStateUsing(fn (InventoryCountLine $record): float => app(SalesInventoryService::class)
                        ->warehouseStockQty((int) $record->item_id, (int) $record->warehouse_id)),
                TextColumn::make('quantity_difference')
                    ->label('الفرق')
                    ->numeric(3),
                TextColumn::make('value_amount')
                    ->label('القيمة')
                    ->numeric(3),
                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y-m-d H:i'),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (InventoryCountLine $record): bool => app(InventoryCountService::class)->canDeleteLine($record))
                    ->before(function (InventoryCountLine $record): void {
                        try {
                            app(InventoryCountService::class)->reverseLine($record);
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->warning()
                                ->send();

                            throw $exception;
                        } catch (Throwable) {
                            Notification::make()
                                ->title('تعذر حذف سطر الجرد')
                                ->danger()
                                ->send();

                            throw new RuntimeException('تعذر حذف سطر الجرد');
                        }
                    }),
            ])
            ->checkIfRecordIsSelectableUsing(
                fn (InventoryCountLine $record): bool => app(InventoryCountService::class)->canDeleteLine($record),
            );
    }
}
