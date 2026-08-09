<?php

namespace App\Filament\Market\Resources\SupplierPayments\Tables;

use App\Enums\ReceiptTransactionKind;
use App\Models\SupplierPayment;
use App\Services\Payments\SupplierPaymentService;
use App\Services\Pdf\PaymentReceiptVoucherPdfService;
use App\Support\PdfDownload;
use App\Support\ErpNumber;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SupplierPaymentsTable
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
                TextColumn::make('payment_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('المورد')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('paymentMethod.name')
                    ->label('طريقة الدفع')
                    ->description(fn (SupplierPayment $record): ?string => $record->bankAccount?->name ?? $record->cashBox?->name),
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
                SelectFilter::make('supplier_id')
                    ->label('مورد معين')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('warehouse_id')
                    ->label('مخزن معين')
                    ->relationship('warehouse', 'name'),
                Filter::make('invoice_payments')
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
                Filter::make('payment_date')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                Action::make('printVoucher')
                    ->label('طباعة')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->iconButton()
                    ->color('info')
                    ->action(fn (SupplierPayment $record) => PdfDownload::streamed(
                        app(PaymentReceiptVoucherPdfService::class)->supplierPayment($record),
                    )),
                EditAction::make()
                    ->visible(fn (SupplierPayment $record): bool => (
                        (int) $record->transaction_kind->value < ReceiptTransactionKind::WithInvoicePayment->value
                        || Auth::user()?->can('الغاء ايصالات موردين')
                        || Auth::user()?->is_prog
                    )),
                DeleteAction::make()
                    ->visible(fn (SupplierPayment $record): bool => (
                        (int) $record->transaction_kind->value < ReceiptTransactionKind::WithInvoicePayment->value
                        || Auth::user()?->can('الغاء ايصالات موردين')
                        || Auth::user()?->is_prog
                    ))
                    ->after(function (SupplierPayment $record): void {
                        app(SupplierPaymentService::class)->afterDeleted($record);
                    }),
            ]);
    }
}
