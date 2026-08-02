<?php

namespace App\Services\Payments;

use App\Enums\ReceiptTransactionKind;
use App\Models\CashBox;
use App\Models\PurchaseInvoice;
use App\Models\SupplierPayment;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class SupplierPaymentService
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

        $data['flow_direction'] = in_array($kind, $this->paymentKinds(), true) ? 1 : 0;

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
            $invoiceId = $data['purchase_invoice_id'] ?? null;

            if (! $invoiceId) {
                throw new RuntimeException('يجب اختيار الفاتورة');
            }

            $warehouseId = PurchaseInvoice::query()->whereKey($invoiceId)->value('warehouse_id');

            if (! $warehouseId) {
                throw new RuntimeException('الفاتورة لا تحتوي على مخزن');
            }

            $data['warehouse_id'] = $warehouseId;
        } elseif (ReceiptTransactionKind::isInvoiceLinked($kind) && ! empty($data['purchase_invoice_id'])) {
            $data['warehouse_id'] = PurchaseInvoice::query()
                ->whereKey($data['purchase_invoice_id'])
                ->value('warehouse_id') ?? $data['warehouse_id'] ?? null;
        } elseif (Auth::user()?->warehouse_id) {
            $data['warehouse_id'] = Auth::user()->warehouse_id;
        }

        if (! ReceiptTransactionKind::isInvoiceLinked($kind)) {
            $data['purchase_invoice_id'] = null;
        }

        $data['created_by'] ??= Auth::id();

        return $data;
    }

    public function syncPurchaseInvoice(?int $purchaseInvoiceId): void
    {
        if (! $purchaseInvoiceId) {
            return;
        }

        $invoice = PurchaseInvoice::query()->find($purchaseInvoiceId);

        if (! $invoice) {
            return;
        }

        $paidOut = (float) SupplierPayment::query()
            ->where('purchase_invoice_id', $purchaseInvoiceId)
            ->whereIn('transaction_kind', $this->paymentKinds())
            ->sum('amount');

        $collected = (float) SupplierPayment::query()
            ->where('purchase_invoice_id', $purchaseInvoiceId)
            ->whereIn('transaction_kind', $this->collectionKinds())
            ->sum('amount');

        $invoice->forceFill([
            'amount_paid' => $paidOut - $collected,
        ])->saveQuietly();

        $invoice->recalculateTotals();
    }

    public function afterSaved(SupplierPayment $payment, ?int $previousInvoiceId = null): void
    {
        if ($previousInvoiceId && $previousInvoiceId !== $payment->purchase_invoice_id) {
            $this->syncPurchaseInvoice($previousInvoiceId);
        }

        if ($payment->purchase_invoice_id) {
            $this->syncPurchaseInvoice((int) $payment->purchase_invoice_id);
        }
    }

    public function afterDeleted(SupplierPayment $payment): void
    {
        if ($payment->purchase_invoice_id) {
            $this->syncPurchaseInvoice((int) $payment->purchase_invoice_id);
        }
    }
}
