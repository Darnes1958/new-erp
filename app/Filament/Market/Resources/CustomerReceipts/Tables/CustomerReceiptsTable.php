<?php

namespace App\Filament\Market\Resources\CustomerReceipts\Tables;

use App\Enums\ReceiptTransactionKind;
use App\Models\CustomerReceipt;
use App\Services\Payments\CustomerReceiptService;
use App\Support\ErpNumber;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CustomerReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('receipt_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('الزبون')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('paymentMethod.name')
                    ->label('طريقة الدفع')
                    ->description(fn (CustomerReceipt $record): ?string => $record->bankAccount?->name ?? $record->cashBox?->name),
                TextColumn::make('transaction_kind')
                    ->label('البيان')
                    ->badge(),
                TextColumn::make('warehouse.name')
                    ->label('المخزن')
                    ->toggleable(),
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->formatStateUsing(fn ($state): string => ErpNumber::money($state))
                    ->sortable(),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('customer_id')
                    ->label('زبون معين')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('warehouse_id')
                    ->label('مخزن معين')
                    ->relationship('warehouse', 'name'),
                Filter::make('invoice_receipts')
                    ->label('إيصالات فاتورة')
                    ->query(fn (Builder $query): Builder => $query->whereIn('transaction_kind', [
                        ReceiptTransactionKind::InvoiceCollection->value,
                        ReceiptTransactionKind::InvoicePayment->value,
                    ])),
                Filter::make('collections')
                    ->label('إيصالات قبض')
                    ->query(fn (Builder $query): Builder => $query->where('transaction_kind', ReceiptTransactionKind::Collection->value)),
                Filter::make('payments')
                    ->label('إيصالات دفع')
                    ->query(fn (Builder $query): Builder => $query->where('transaction_kind', ReceiptTransactionKind::Payment->value)),
                Filter::make('receipt_date')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('receipt_date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('receipt_date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (CustomerReceipt $record): bool => Auth::user()?->can('تعديل ايصالات زبائن') || Auth::user()?->is_prog),
                DeleteAction::make()
                    ->visible(fn (CustomerReceipt $record): bool => (
                        (int) $record->transaction_kind->value < ReceiptTransactionKind::WithInvoicePayment->value
                        || Auth::user()?->can('الغاء ايصالات زبائن')
                        || Auth::user()?->can('االغاء ايصالات زبائن')
                        || Auth::user()?->is_prog
                    ))
                    ->after(function (CustomerReceipt $record): void {
                        app(CustomerReceiptService::class)->afterDeleted($record);
                    }),
            ]);
    }
}
