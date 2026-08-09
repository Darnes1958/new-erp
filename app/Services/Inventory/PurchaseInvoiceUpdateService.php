<?php

namespace App\Services\Inventory;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseInvoiceLineWork;
use App\Models\PurchaseInvoiceWork;
use App\Models\SupplierPayment;
use App\Services\SystemOperationLogger;
use App\Support\SystemOperationType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceUpdateService
{
    public function updateFromWork(
        PurchaseInvoice $invoice,
        PurchaseInvoiceWork $work,
        Collection $workLines,
        ?int $cashBoxId = null,
        ?int $bankAccountId = null,
    ): ?string {
        if ($workLines->isEmpty()) {
            return 'لم يتم ادخال اصناف';
        }

        if ($workLines->contains(fn (PurchaseInvoiceLineWork $line): bool => (float) $line->unit_cost_primary <= 0)) {
            return 'سعر الشراء لا يجوز أن يكون صفر';
        }

        $inventory = app(PurchaseInventoryService::class);
        $warehouseId = (int) ($work->warehouse_id ?? $invoice->warehouse_id);
        $paymentMethodId = (int) $work->payment_method_id;
        $originalLines = $invoice->lines()->get()->keyBy('id');
        $keptSourceIds = $workLines
            ->pluck('source_purchase_invoice_line_id')
            ->filter()
            ->map(fn ($id) => (int) $id);

        foreach ($originalLines as $originalLine) {
            if ($keptSourceIds->contains($originalLine->id)) {
                continue;
            }

            $consumed = (float) $originalLine->qty_primary - (float) $originalLine->remaining_qty_primary;

            if ($consumed > 0.0001) {
                return 'لا يمكن حذف صنف تم صرف جزء منه';
            }
        }

        if ($originalLines->count() === 1 && $keptSourceIds->isEmpty()) {
            return 'يجب أن تحتوي الفاتورة على صنف واحد على الأقل';
        }

        foreach ($workLines as $workLine) {
            if (! $workLine->source_purchase_invoice_line_id) {
                continue;
            }

            $originalLine = $originalLines->get($workLine->source_purchase_invoice_line_id);

            if (! $originalLine) {
                continue;
            }

            $consumed = (float) $originalLine->qty_primary - (float) $originalLine->remaining_qty_primary;

            if ((float) $workLine->qty_primary < $consumed - 0.0001) {
                return 'الكمية الجديدة أقل من الكمية المصروفة للصنف '.$originalLine->item_id;
            }
        }

        DB::connection($invoice->getConnectionName())->transaction(function () use (
            $invoice,
            $work,
            $workLines,
            $cashBoxId,
            $bankAccountId,
            $inventory,
            $warehouseId,
            $paymentMethodId,
            $originalLines,
            $keptSourceIds,
        ): void {
            foreach ($originalLines as $originalLine) {
                if ($keptSourceIds->contains($originalLine->id)) {
                    continue;
                }

                $inventory->reversePurchaseLine(
                    (int) $originalLine->item_id,
                    (int) $invoice->warehouse_id,
                    (float) $originalLine->qty_primary,
                    (float) $originalLine->qty_secondary,
                    referenceType: PurchaseInvoice::class,
                    referenceId: $invoice->id,
                    movementDate: $work->invoice_date,
                    notes: 'حذف سطر من فاتورة مشتريات رقم '.(string) $invoice->id,
                );

                $originalLine->delete();
            }

            foreach ($workLines as $workLine) {
                if ($workLine->source_purchase_invoice_line_id) {
                    $this->updateExistingLine(
                        $invoice,
                        $work,
                        $workLine,
                        $originalLines->get($workLine->source_purchase_invoice_line_id),
                        $inventory,
                        $warehouseId,
                        $paymentMethodId,
                    );

                    continue;
                }

                $this->createNewLine(
                    $invoice,
                    $work,
                    $workLine,
                    $inventory,
                    $warehouseId,
                    $paymentMethodId,
                );
            }

            $this->syncEntrySupplierPayment($invoice, $work, $cashBoxId, $bankAccountId);

            $invoice->refresh();

            $invoice->update([
                'invoice_date' => $work->invoice_date,
                'supplier_id' => $work->supplier_id,
                'payment_method_id' => $work->payment_method_id,
                'warehouse_id' => $warehouseId,
                'lines_subtotal' => $work->lines_subtotal,
                'discount' => (float) $work->discount,
                'amount_paid' => (float) $work->amount_paid,
                'balance' => (float) $work->lines_subtotal - (float) $work->discount - PurchaseInvoice::totalPaymentsForInvoice((int) $invoice->id),
                'notes' => $work->notes,
            ]);

            PurchaseInvoiceLineWork::query()
                ->where('purchase_invoice_work_id', $work->id)
                ->delete();

            $work->update([
                'source_purchase_invoice_id' => null,
                'lines_subtotal' => 0,
                'discount' => 0,
                'amount_paid' => 0,
                'balance' => 0,
                'supplier_id' => null,
                'invoice_date' => null,
                'payment_method_id' => 1,
                'notes' => '',
            ]);
        });

        SystemOperationLogger::updated(SystemOperationType::PURCHASE, $invoice->id);

        return null;
    }

    protected function updateExistingLine(
        PurchaseInvoice $invoice,
        PurchaseInvoiceWork $work,
        PurchaseInvoiceLineWork $workLine,
        ?PurchaseInvoiceLine $originalLine,
        PurchaseInventoryService $inventory,
        int $warehouseId,
        int $paymentMethodId,
    ): void {
        if (! $originalLine) {
            return;
        }

        $oldQtyPrimary = (float) $originalLine->qty_primary;
        $oldQtySecondary = (float) $originalLine->qty_secondary;
        $newQtyPrimary = (float) $workLine->qty_primary;
        $newQtySecondary = (float) $workLine->qty_secondary;
        $consumedPrimary = $oldQtyPrimary - (float) $originalLine->remaining_qty_primary;
        $consumedSecondary = $oldQtySecondary - (float) $originalLine->remaining_qty_secondary;
        $qtyChanged = (int) $originalLine->item_id !== (int) $workLine->item_id
            || abs($oldQtyPrimary - $newQtyPrimary) > 0.0001
            || abs($oldQtySecondary - $newQtySecondary) > 0.0001;

        if ($qtyChanged) {
            $inventory->reversePurchaseLine(
                (int) $originalLine->item_id,
                (int) $invoice->warehouse_id,
                $oldQtyPrimary,
                $oldQtySecondary,
                referenceType: PurchaseInvoice::class,
                referenceId: $invoice->id,
                movementDate: $work->invoice_date,
                notes: 'تعديل سطر فاتورة مشتريات رقم '.(string) $invoice->id,
            );
        }

        $originalLine->update([
            'item_id' => $workLine->item_id,
            'barcode' => $workLine->barcode,
            'qty_primary' => $newQtyPrimary,
            'qty_secondary' => $newQtySecondary,
            'unit_cost_primary' => $workLine->unit_cost_primary,
            'line_cost_total' => $workLine->line_cost_total,
            'remaining_qty_primary' => $newQtyPrimary - $consumedPrimary,
            'remaining_qty_secondary' => $newQtySecondary - $consumedSecondary,
            'expiry_date' => $workLine->expiry_date,
        ]);

        if ($qtyChanged) {
            $inventory->applyPurchaseLine(
                (int) $workLine->item_id,
                $warehouseId,
                $newQtyPrimary,
                $newQtySecondary,
                $paymentMethodId,
                (float) $workLine->unit_cost_primary,
                referenceType: PurchaseInvoice::class,
                referenceId: $invoice->id,
                movementDate: $work->invoice_date,
                notes: 'تعديل فاتورة مشتريات رقم '.(string) $invoice->id,
            );
        } else {
            $inventory->syncBuyPrice(
                (int) $workLine->item_id,
                $paymentMethodId,
                (float) $workLine->unit_cost_primary,
            );
        }
    }

    protected function createNewLine(
        PurchaseInvoice $invoice,
        PurchaseInvoiceWork $work,
        PurchaseInvoiceLineWork $workLine,
        PurchaseInventoryService $inventory,
        int $warehouseId,
        int $paymentMethodId,
    ): void {
        PurchaseInvoiceLine::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'item_id' => $workLine->item_id,
            'barcode' => $workLine->barcode,
            'qty_primary' => $workLine->qty_primary,
            'qty_secondary' => $workLine->qty_secondary,
            'unit_cost_primary' => $workLine->unit_cost_primary,
            'line_cost_total' => $workLine->line_cost_total,
            'remaining_qty_primary' => $workLine->qty_primary,
            'remaining_qty_secondary' => $workLine->qty_secondary,
            'expiry_date' => $workLine->expiry_date,
            'created_by' => Auth::id(),
        ]);

        $inventory->applyPurchaseLine(
            (int) $workLine->item_id,
            $warehouseId,
            (float) $workLine->qty_primary,
            (float) $workLine->qty_secondary,
            $paymentMethodId,
            (float) $workLine->unit_cost_primary,
            referenceType: PurchaseInvoice::class,
            referenceId: $invoice->id,
            movementDate: $work->invoice_date,
            notes: 'إضافة سطر لفاتورة مشتريات رقم '.(string) $invoice->id,
        );
    }

    protected function syncEntrySupplierPayment(
        PurchaseInvoice $invoice,
        PurchaseInvoiceWork $work,
        ?int $cashBoxId,
        ?int $bankAccountId,
    ): void {
        $payment = SupplierPayment::query()
            ->where('purchase_invoice_id', $invoice->id)
            ->where('transaction_kind', 5)
            ->first();

        if ((float) $work->amount_paid <= 0) {
            $payment?->delete();

            return;
        }

        $payload = [
            'payment_date' => $work->invoice_date,
            'supplier_id' => $work->supplier_id,
            'payment_method_id' => $work->payment_method_id,
            'amount' => (float) $work->amount_paid,
            'warehouse_id' => $work->warehouse_id,
            'cash_box_id' => $cashBoxId,
            'bank_account_id' => $bankAccountId,
            'notes' => 'فاتورة مشتريات رقم '.(string) $invoice->id,
        ];

        if ($payment) {
            $payment->update($payload);

            return;
        }

        SupplierPayment::query()->create([
            ...$payload,
            'purchase_invoice_id' => $invoice->id,
            'transaction_kind' => 5,
            'flow_direction' => 1,
            'created_by' => Auth::id(),
        ]);
    }
}
