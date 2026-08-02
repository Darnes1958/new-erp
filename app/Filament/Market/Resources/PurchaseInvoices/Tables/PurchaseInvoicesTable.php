<?php

namespace App\Filament\Market\Resources\PurchaseInvoices\Tables;

use App\Filament\Market\Resources\PurchaseInvoices\Pages\EditBuy;
use App\Filament\Market\Resources\PurchaseInvoices\Pages\PurchaseReturnEntry;
use App\Models\PurchaseInvoice;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(false)
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
                ViewAction::make()->iconButton(),
                Action::make('print')
                    ->label('طباعة')
                    ->icon('heroicon-o-printer')
                    ->iconButton()
                    ->url(fn (Model $record): string => route('pdf.purchase-invoice', ['purchaseInvoice' => $record]))
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
                DeleteAction::make()->iconButton(),
            ]);
    }
}
