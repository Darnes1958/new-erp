<?php

namespace App\Enums;

enum ReceiptListKind: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';

    public function reportTitle(): string
    {
        return match ($this) {
            self::Customer => 'إيصالات زبائن',
            self::Supplier => 'إيصالات موردين',
        };
    }

    public function partyLabel(): string
    {
        return match ($this) {
            self::Customer => 'الزبون',
            self::Supplier => 'المورد',
        };
    }

    public function dateFilterName(): string
    {
        return match ($this) {
            self::Customer => 'receipt_date',
            self::Supplier => 'payment_date',
        };
    }

    public function partyFilterName(): string
    {
        return match ($this) {
            self::Customer => 'customer_id',
            self::Supplier => 'supplier_id',
        };
    }

    public function invoiceFilterName(): string
    {
        return match ($this) {
            self::Customer => 'invoice_receipts',
            self::Supplier => 'invoice_payments',
        };
    }

    public function partyModelClass(): string
    {
        return match ($this) {
            self::Customer => \App\Models\Customer::class,
            self::Supplier => \App\Models\Supplier::class,
        };
    }

    /** @return list<string> */
    public function eagerLoads(): array
    {
        return match ($this) {
            self::Customer => ['customer', 'paymentMethod', 'bankAccount', 'cashBox', 'warehouse'],
            self::Supplier => ['supplier', 'paymentMethod', 'bankAccount', 'cashBox', 'warehouse'],
        };
    }

    public function downloadFileStem(): string
    {
        return match ($this) {
            self::Customer => 'customer-receipts',
            self::Supplier => 'supplier-payments',
        };
    }
}
