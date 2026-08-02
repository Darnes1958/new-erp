<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReceiptTransactionKind: int implements HasColor, HasLabel
{
    case Collection = 1;
    case Payment = 2;
    case InvoiceCollection = 3;
    case InvoicePayment = 4;
    case WithInvoicePayment = 5;
    case WithInvoiceCollection = 6;

    public function getLabel(): string
    {
        return match ($this) {
            self::Collection => 'قبض',
            self::Payment => 'دفع',
            self::InvoiceCollection => 'قبض فاتورة',
            self::InvoicePayment => 'دفع فاتورة',
            self::WithInvoicePayment => 'دفع مع فاتورة',
            self::WithInvoiceCollection => 'قبض مع فاتورة',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Collection, self::InvoiceCollection, self::WithInvoiceCollection => 'success',
            self::Payment, self::InvoicePayment, self::WithInvoicePayment => 'danger',
        };
    }

    /** @return list<int> */
    public static function manualEntryValues(): array
    {
        return [
            self::Collection->value,
            self::Payment->value,
            self::InvoiceCollection->value,
            self::InvoicePayment->value,
        ];
    }

    /** @return list<int> */
    public static function invoiceLinkedValues(): array
    {
        return [
            self::InvoiceCollection->value,
            self::InvoicePayment->value,
            self::WithInvoicePayment->value,
            self::WithInvoiceCollection->value,
        ];
    }

    public static function isInvoiceLinked(int $kind): bool
    {
        return in_array($kind, self::invoiceLinkedValues(), true);
    }

    /** @return list<int> */
    public static function manualInvoiceReceiptValues(): array
    {
        return [
            self::InvoiceCollection->value,
            self::InvoicePayment->value,
        ];
    }

    public static function requiresInvoiceWarehouse(int $kind): bool
    {
        return in_array($kind, self::manualInvoiceReceiptValues(), true);
    }
}
