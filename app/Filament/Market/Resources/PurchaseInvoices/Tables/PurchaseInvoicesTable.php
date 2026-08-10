<?php

namespace App\Filament\Market\Resources\PurchaseInvoices\Tables;

use App\Filament\Market\Resources\PurchaseInvoices\Pages\EditBuy;
use App\Filament\Market\Resources\PurchaseInvoices\Pages\PurchaseReturnEntry;
use App\Filament\Market\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use App\Services\Inventory\PurchaseInvoiceCancelService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\View;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(false)
            ->extraAttributes(['class' => 'purchase-invoices-table'])
            ->columns([
                Split::make([
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
                    TextColumn::make('net_total')
                        ->label('الإجمالي')
                        ->getStateUsing(fn (PurchaseInvoice $record): float => (float) $record->lines_subtotal - (float) $record->discount)
                        ->numeric(3)
                        ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw("(lines_subtotal - discount) {$direction}")),
                    TextColumn::make('amount_paid')
                        ->label('المدفوع')
                        ->numeric(3),
                    TextColumn::make('balance')
                        ->label('الباقي')
                        ->numeric(3),
                    TextColumn::make('lines_count')
                        ->label('البنود')
                        ->counts('lines')
                        ->badge()
                        ->color('gray'),
                    TextColumn::make('warehouse.name')
                        ->label('المخزن')
                        ->toggleable(),
                    TextColumn::make('notes')
                        ->label('ملاحظات')
                        ->limit(30)
                        ->toggleable(),
                ])->from('lg'),
                Panel::make([
                    View::make('filament.market.tables.purchase-invoice-lines')
                        ->grow(),
                ])
                    ->collapsible()
                    ->grow()
                    ->extraAttributes([
                        'class' => '!w-full !max-w-none !p-0 !bg-transparent !shadow-none !ring-0',
                    ]),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('المورد')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('warehouse_id')
                    ->label('المخزن')
                    ->relationship('warehouse', 'name'),
                Filter::make('invoice_date')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('invoice_date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('invoice_date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->url(fn (PurchaseInvoice $record): string => PurchaseInvoiceResource::getUrl('view', ['record' => $record])),
                Action::make('print')
                    ->label('طباعة')
                    ->icon('heroicon-o-printer')
                    ->iconButton()
                    ->url(fn (Model $record): string => route('pdf.purchase-invoice', ['purchaseInvoice' => $record]))
                    ->openUrlInNewTab(),
                Action::make('print_item_prices')
                    ->tooltip('طباعة اسعار الأصناف')
                    ->icon('heroicon-s-printer')
                    ->iconButton()
                    ->iconSize(IconSize::Small)
                    ->color('primary')
                    ->url(fn (Model $record): string => route('pdf.purchase-invoice-item-prices', ['purchaseInvoice' => $record]))
                    ->openUrlInNewTab(),
                Action::make('edit_buy')
                    ->label('تعديل')
                    ->icon('heroicon-m-pencil')
                    ->iconButton()
                    ->color('info')
                    ->url(fn (Model $record): string => EditBuy::getUrl(['record' => $record])),
                Action::make('purchase_return')
                    ->label('ترجيع')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->iconButton()
                    ->color('primary')
                    ->tooltip('ترجيع')
                    ->url(fn (Model $record): string => PurchaseReturnEntry::getUrl(['record' => $record])),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (PurchaseInvoice $record): bool => $record->canBeDeleted())
                    ->before(function (PurchaseInvoice $record): void {
                        try {
                            $record->assertCanBeDeleted();
                        } catch (\RuntimeException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->warning()
                                ->send();

                            throw $exception;
                        }
                    })
                    ->using(fn (PurchaseInvoice $record) => app(PurchaseInvoiceCancelService::class)->cancel($record)),
            ]);
    }
}
