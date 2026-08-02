<?php

namespace App\Filament\Market\Resources\SupplierPayments\Schemas;

use App\Enums\ReceiptTransactionKind;
use App\Models\CashBox;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Support\ErpNumber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SupplierPaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الإيصال')
                ->schema([
                Radio::make('transaction_kind')
                    ->label('نوع الإيصال')
                    ->inline()
                    ->default(ReceiptTransactionKind::Collection->value)
                    ->live()
                    ->options(function (string $operation): array {
                        $cases = $operation === 'create'
                            ? [
                                ReceiptTransactionKind::Collection,
                                ReceiptTransactionKind::Payment,
                                ReceiptTransactionKind::InvoiceCollection,
                                ReceiptTransactionKind::InvoicePayment,
                            ]
                            : ReceiptTransactionKind::cases();

                        return collect($cases)
                            ->mapWithKeys(fn (ReceiptTransactionKind $kind): array => [$kind->value => $kind->getLabel()])
                            ->all();
                    })
                    ->disabled(fn ($state): bool => in_array(
                        (int) (is_object($state) ? $state->value : $state),
                        [
                            ReceiptTransactionKind::WithInvoicePayment->value,
                            ReceiptTransactionKind::WithInvoiceCollection->value,
                        ],
                        true,
                    ))
                    ->afterStateUpdated(function ($state, callable $set, Get $get): void {
                        $kind = (int) (is_object($state) ? $state->value : $state);

                        if (ReceiptTransactionKind::requiresInvoiceWarehouse($kind)) {
                            $invoiceId = $get('purchase_invoice_id');

                            if ($invoiceId) {
                                $set('warehouse_id', PurchaseInvoice::query()->whereKey($invoiceId)->value('warehouse_id'));
                            }

                            return;
                        }

                        $set('warehouse_id', Auth::user()?->warehouse_id);
                    })
                    ->columnSpanFull(),
                Select::make('supplier_id')
                    ->label('المورد')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->createOptionForm([
                        Section::make('ادخال مورد جديد')->schema([
                            TextInput::make('name')
                                ->required()
                                ->unique(Supplier::class)
                                ->label('الاسم'),
                            TextInput::make('address')
                                ->label('العنوان'),
                            Hidden::make('created_by')
                                ->default(fn () => Auth::id()),
                        ]),
                    ]),
                Select::make('purchase_invoice_id')
                    ->label('رقم الفاتورة')
                    ->relationship(
                        'purchaseInvoice',
                        'id',
                        fn (Builder $query, Get $get): Builder => $query
                            ->when(
                                $get('supplier_id'),
                                fn (Builder $query, $supplierId): Builder => $query->where('supplier_id', $supplierId),
                            )
                            ->orderByDesc('id'),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (PurchaseInvoice $record): string => "{$record->id} ({$record->supplier->name} - ".ErpNumber::money($record->lines_subtotal).')',
                    )
                    ->searchable()
                    ->preload()
                    ->required(fn (Get $get): bool => ReceiptTransactionKind::requiresInvoiceWarehouse((int) $get('transaction_kind')))
                    ->visible(fn (Get $get): bool => ReceiptTransactionKind::isInvoiceLinked((int) $get('transaction_kind')))
                    ->live()
                    ->afterStateUpdated(function (?string $state, callable $set, Get $get): void {
                        if (! ReceiptTransactionKind::requiresInvoiceWarehouse((int) $get('transaction_kind'))) {
                            return;
                        }

                        if (! $state) {
                            $set('warehouse_id', null);

                            return;
                        }

                        $set('warehouse_id', PurchaseInvoice::query()->whereKey($state)->value('warehouse_id'));
                    }),
                Select::make('payment_method_id')
                    ->label('طريقة الدفع')
                    ->relationship(
                        'paymentMethod',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn('id', [1, 2]),
                    )
                    ->searchable()
                    ->preload()
                    ->default(1)
                    ->live()
                    ->required(),
                DatePicker::make('payment_date')
                    ->label('التاريخ')
                    ->default(now())
                    ->required(),
                TextInput::make('amount')
                    ->label('المبلغ')
                    ->required()
                    ->numeric()
                    ->minValue(0.001),
                Select::make('bank_account_id')
                    ->label('المصرف')
                    ->relationship('bankAccount', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => (int) $get('payment_method_id') === 2)
                    ->required(fn (Get $get): bool => (int) $get('payment_method_id') === 2),
                Select::make('cash_box_id')
                    ->label('الخزينة')
                    ->relationship('cashBox', 'name')
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?int => CashBox::query()
                        ->where('assigned_user_id', Auth::id())
                        ->where('is_active', true)
                        ->value('id'))
                    ->disabled(fn (): bool => CashBox::query()
                        ->where('assigned_user_id', Auth::id())
                        ->where('is_active', true)
                        ->exists())
                    ->visible(fn (Get $get): bool => (int) $get('payment_method_id') === 1)
                    ->required(fn (Get $get): bool => (int) $get('payment_method_id') === 1),
                Select::make('warehouse_id')
                    ->label('المخزن')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?int => Auth::user()?->warehouse_id)
                    ->disabled(fn (Get $get): bool => ReceiptTransactionKind::isInvoiceLinked((int) $get('transaction_kind'))
                        || Auth::user()?->warehouse_id !== null)
                    ->required(fn (Get $get): bool => ReceiptTransactionKind::requiresInvoiceWarehouse((int) $get('transaction_kind'))),
                TextInput::make('notes')
                    ->label('ملاحظات')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ])
                ->columns(3)
                ->columnSpanFull(),
        ]);
    }
}
