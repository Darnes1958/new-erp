<?php

namespace App\Services\Payments;

use App\Enums\ReceiptTransactionKind;
use App\Models\CashBox;
use App\Models\CustomerReceipt;
use App\Models\SalesInvoice;
use App\Services\SystemOperationLogger;
use App\Support\SystemOperationContext;
use App\Support\SystemOperationType;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class CustomerReceiptService
{
    /** @return list<int> */
    public function collectionKinds(): array
    {
        return [
            ReceiptTransactionKind::Collection->value,
            ReceiptTransactionKind::InvoiceCollection->value,
            ReceiptTransactionKind::WithInvoiceCollection->value,
        ];
    }

    /** @return list<int> */
    public function paymentKinds(): array
    {
        return [
            ReceiptTransactionKind::Payment->value,
            ReceiptTransactionKind::InvoicePayment->value,
            ReceiptTransactionKind::WithInvoicePayment->value,
        ];
    }

    public function prepareAttributes(array $data): array
    {
        $kindValue = $data['transaction_kind'];
        $kind = is_object($kindValue) ? $kindValue->value : (int) $kindValue;
        $data['transaction_kind'] = $kind;

        $paymentMethodId = (int) ($data['payment_method_id'] ?? 1);

        $data['flow_direction'] = in_array($kind, $this->collectionKinds(), true) ? 0 : 1;

        if ($paymentMethodId === 1) {
            $data['bank_account_id'] = null;

            if (empty($data['cash_box_id'])) {
                $data['cash_box_id'] = CashBox::query()
                    ->where('assigned_user_id', Auth::id())
                    ->where('is_active', true)
                    ->value('id');
            }

            if (empty($data['cash_box_id'])) {
                throw new RuntimeException('يجب اختيار الخزينة');
            }
        }

        if ($paymentMethodId === 2) {
            $data['cash_box_id'] = null;

            if (empty($data['bank_account_id'])) {
                throw new RuntimeException('يجب اختيار المصرف');
            }
        }

        if (ReceiptTransactionKind::requiresInvoiceWarehouse($kind)) {
            $invoiceId = $data['sales_invoice_id'] ?? null;

            if (! $invoiceId) {
                throw new RuntimeException('يجب اختيار الفاتورة');
            }

            $warehouseId = SalesInvoice::query()->whereKey($invoiceId)->value('warehouse_id');

            if (! $warehouseId) {
                throw new RuntimeException('الفاتورة لا تحتوي على مخزن');
            }

            $data['warehouse_id'] = $warehouseId;
        } elseif (ReceiptTransactionKind::isInvoiceLinked($kind) && ! empty($data['sales_invoice_id'])) {
            $data['warehouse_id'] = SalesInvoice::query()
                ->whereKey($data['sales_invoice_id'])
                ->value('warehouse_id') ?? $data['warehouse_id'] ?? null;
        } elseif (Auth::user()?->warehouse_id) {
            $data['warehouse_id'] = Auth::user()->warehouse_id;
        }

        if (! ReceiptTransactionKind::isInvoiceLinked($kind)) {
            $data['sales_invoice_id'] = null;
        }

        $data['created_by'] ??= Auth::id();

        return $data;
    }

    public function syncSalesInvoice(?int $salesInvoiceId): void
    {
        if (! $salesInvoiceId) {
            return;
        }

        $invoice = SalesInvoice::query()->find($salesInvoiceId);

        if (! $invoice) {
            return;
        }

        $collected = (float) CustomerReceipt::query()
            ->where('sales_invoice_id', $salesInvoiceId)
            ->whereIn('transaction_kind', $this->collectionKinds())
            ->sum('amount');

        $paidOut = (float) CustomerReceipt::query()
            ->where('sales_invoice_id', $salesInvoiceId)
            ->whereIn('transaction_kind', $this->paymentKinds())
            ->sum('amount');

        $paid = $collected - $paidOut;

        $invoice->forceFill([
            'amount_paid' => $paid,
            'balance' => (float) $invoice->grand_total - $paid,
        ])->saveQuietly();
    }

    public function afterSaved(CustomerReceipt $receipt, ?int $previousInvoiceId = null): void
    {
        if ($previousInvoiceId && $previousInvoiceId !== $receipt->sales_invoice_id) {
            $this->syncSalesInvoice($previousInvoiceId);
        }

        if ($receipt->sales_invoice_id) {
            $this->syncSalesInvoice((int) $receipt->sales_invoice_id);
        }

        SystemOperationLogger::updated(
            SystemOperationType::CUSTOMER_RECEIPT,
            $receipt->id,
            SystemOperationContext::customer($receipt->customer_id ? (int) $receipt->customer_id : null),
        );
    }

    public function afterDeleted(CustomerReceipt $receipt): void
    {
        if ($receipt->sales_invoice_id) {
            $this->syncSalesInvoice((int) $receipt->sales_invoice_id);
        }

        SystemOperationLogger::cancelled(
            SystemOperationType::CUSTOMER_RECEIPT,
            $receipt->id,
            SystemOperationContext::customer($receipt->customer_id ? (int) $receipt->customer_id : null),
        );
    }
}
