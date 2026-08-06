<?php

namespace App\Filament\Market\Resources\WarehouseTransfers\Tables;

use App\Models\WarehouseTransfer;
use App\Services\Inventory\WarehouseTransferService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class WarehouseTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم الآلي')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('transfer_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('warehouseFrom.name')
                    ->label('مــــــــن')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('warehouseTo.name')
                    ->label('إلــــــــي')
                    ->sortable()
                    ->searchable(),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->emptyStateHeading('لا توجد بيانات')
            ->filters([
                SelectFilter::make('warehouse_from_id')
                    ->label('من')
                    ->relationship('warehouseFrom', 'name'),
                SelectFilter::make('warehouse_to_id')
                    ->label('إلى')
                    ->relationship('warehouseTo', 'name'),
                Filter::make('transfer_date')
                    ->label('التاريخ')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('من تاريخ'),
                        DatePicker::make('date_to')
                            ->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('transfer_date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('transfer_date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                Action::make('lines')
                    ->iconButton()
                    ->iconSize(IconSize::Small)
                    ->icon(Heroicon::ListBullet)
                    ->color('success')
                    ->modalHeading(false)
                    ->modalSubmitAction(false)
                    ->modalCancelAction(fn (Action $action) => $action->label('عودة'))
                    ->modalContent(fn (WarehouseTransfer $record): View => view(
                        'filament.market.pages.warehouse-transfer-lines',
                        ['transfer' => $record->load('lines.item')],
                    )),
                Action::make('print')
                    ->icon('heroicon-o-printer')
                    ->iconButton()
                    ->color('info')
                    ->url(fn (WarehouseTransfer $record): string => route('pdf.warehouse-transfer', ['warehouseTransfer' => $record]))
                    ->openUrlInNewTab(),
                Action::make('delete')
                    ->icon('heroicon-o-trash')
                    ->iconButton()
                    ->iconSize(IconSize::Small)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('إلغاء إذن النقل')
                    ->action(function (WarehouseTransfer $record): void {
                        try {
                            app(WarehouseTransferService::class)->reverse($record);
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->warning()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()->title('تم إلغاء إذن النقل')->success()->send();
                    }),
            ]);
    }
}
