<?php

namespace App\Services\Inventory;

use App\Models\CustomerReceipt;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SalesInvoiceLineWork;
use App\Models\SalesInvoiceWork;
use App\Services\Installments\InstallmentContractService;
use App\Services\Payments\CustomerReceiptService;
use App\Services\SystemOperationLogger;
use App\Support\SystemOperationContext;
use App\Support\SystemOperationType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesInvoiceUpdateService
{
    public function updateFromWork(
        SalesInvoice $invoice,
        SalesInvoiceWork $work,
        Collection $workLines,
        ?int $cashBoxId = null,
        ?int $bankAccountId = null,
    ): ?string {
        if ($workLines->isEmpty()) {
            return 'لم يتم ادخال اصناف';
        }

        if ($workLines->contains(fn (SalesInvoiceLineWork $line): bool => (float) $line->unit_price_primary <= 0)) {
            return 'سعر البيع لا يجوز أن يكون صفر';
        }

        $inventory = app(SalesInventoryService::class);
        $warehouseId = (int) ($work->warehouse_id ?? $invoice->warehouse_id);
        $originalLines = $invoice->lines()->get()->keyBy('id');
        $keptSourceIds = $workLines
            ->pluck('source_sales_invoice_line_id')
            ->filter()
            ->map(fn ($id) => (int) $id);

        foreach ($originalLines as $originalLine) {
            if ($keptSourceIds->contains($originalLine->id)) {
                continue;
            }

            if ($originalLine->sales_return_id !== null) {
                return 'لا يمكن حذف بند تم ترجيعه';
            }
        }

        if ($originalLines->count() === 1 && $keptSourceIds->isEmpty()) {
            return 'يجب أن تحتوي الفاتورة على صنف واحد على الأقل';
        }

        foreach ($workLines as $workLine) {
            if (! $workLine->source_sales_invoice_line_id) {
                continue;
            }

            $originalLine = $originalLines->get($workLine->source_sales_invoice_line_id);

            if (! $originalLine) {
                continue;
            }

            if ($originalLine->sales_return_id !== null) {
                $qtyChanged = abs((float) $originalLine->qty_primary - (float) $workLine->qty_primary) > 0.0001
                    || abs((float) $originalLine->qty_secondary - (float) $workLine->qty_secondary) > 0.0001;
                $itemChanged = (int) $originalLine->item_id !== (int) $workLine->item_id;

                if ($qtyChanged || $itemChanged) {
                    return 'لا يمكن تعديل كمية أو صنف بند تم ترجيعه';
                }
            }
        }

        $contractError = app(InstallmentContractService::class)->validateSalesInvoiceUpdate(
            $invoice,
            (float) $work->grand_total,
            (float) $work->balance,
        );

        if ($contractError !== null) {
            return $contractError;
        }

        DB::connection($invoice->getConnectionName())->transaction(function () use (
            $invoice,
            $work,
            $workLines,
            $cashBoxId,
            $bankAccountId,
            $inventory,
            $warehouseId,
            $originalLines,
            $keptSourceIds,
        ): void {
            foreach ($originalLines as $originalLine) {
                if ($keptSourceIds->contains($originalLine->id)) {
                    continue;
                }

                $inventory->reverseSalesLine(
                    $originalLine,
                    (int) $invoice->warehouse_id,
                    movementDate: $work->invoice_date,
                    notes: 'حذف سطر من فاتورة مبيعات رقم '.(string) $invoice->id,
                );

                $originalLine->delete();
            }

            foreach ($workLines as $workLine) {
                if ($workLine->source_sales_invoice_line_id) {
                    $this->updateExistingLine(
                        $invoice,
                        $work,
                        $workLine,
                        $originalLines->get($workLine->source_sales_invoice_line_id),
                        $inventory,
                        $warehouseId,
                    );

                    continue;
                }

                $this->createNewLine(
                    $invoice,
                    $work,
                    $workLine,
                    $inventory,
                    $warehouseId,
                );
            }

            $this->syncEntryCustomerReceipt($invoice, $work, $cashBoxId, $bankAccountId);

            $invoice->refresh();

            $invoice->update([
                'invoice_date' => $work->invoice_date,
                'customer_id' => $work->customer_id,
                'payment_method_id' => $work->payment_method_id,
                'warehouse_id' => $warehouseId,
                'is_retail' => (bool) $work->is_retail,
                'lines_subtotal' => $work->lines_subtotal,
                'extra_cost' => (float) $work->extra_cost,
                'rate_markup' => (float) $work->rate_markup,
                'difference_amount' => (float) $work->difference_amount,
                'discount' => (float) $work->discount,
                'grand_total' => (float) $work->grand_total,
                'amount_paid' => (float) $work->amount_paid,
                'balance' => (float) $work->balance,
                'notes' => $work->notes,
            ]);

            app(InstallmentContractService::class)->syncFromSalesInvoice($invoice->refresh());

            SalesInvoiceLineWork::query()
                ->where('sales_invoice_work_id', $work->id)
                ->delete();

            $work->update([
                'source_sales_invoice_id' => null,
                'lines_subtotal' => 0,
                'extra_cost' => 0,
                'rate_markup' => 0,
                'difference_amount' => 0,
                'discount' => 0,
                'grand_total' => 0,
                'amount_paid' => 0,
                'balance' => 0,
                'customer_id' => null,
                'invoice_date' => null,
                'payment_method_id' => 1,
                'notes' => '',
            ]);
        });

        SystemOperationLogger::updated(
            SystemOperationType::SALE,
            $invoice->id,
            SystemOperationContext::customer($invoice->customer_id ? (int) $invoice->customer_id : null),
        );

        return null;
    }

    protected function updateExistingLine(
        SalesInvoice $invoice,
        SalesInvoiceWork $work,
        SalesInvoiceLineWork $workLine,
        ?SalesInvoiceLine $originalLine,
        SalesInventoryService $inventory,
        int $warehouseId,
    ): void {
        if (! $originalLine) {
            return;
        }

        if ($originalLine->sales_return_id !== null) {
            $originalLine->update([
                'barcode' => $workLine->barcode,
                'unit_price_primary' => $workLine->unit_price_primary,
                'unit_price_secondary' => $workLine->unit_price_secondary,
                'line_total' => $workLine->line_total,
            ]);

            return;
        }

        $oldQtyPrimary = (float) $originalLine->qty_primary;
        $oldQtySecondary = (float) $originalLine->qty_secondary;
        $newQtyPrimary = (float) $workLine->qty_primary;
        $newQtySecondary = (float) $workLine->qty_secondary;
        $itemChanged = (int) $originalLine->item_id !== (int) $workLine->item_id;
        $qtyChanged = abs($oldQtyPrimary - $newQtyPrimary) > 0.0001
            || abs($oldQtySecondary - $newQtySecondary) > 0.0001;
        $priceChanged = abs((float) $originalLine->unit_price_primary - (float) $workLine->unit_price_primary) > 0.0001;

        if ($itemChanged || $qtyChanged || $priceChanged) {
            $inventory->reverseSalesLine(
                $originalLine,
                (int) $invoice->warehouse_id,
                movementDate: $work->invoice_date,
                notes: 'تعديل سطر فاتورة مبيعات رقم '.(string) $invoice->id,
            );
        }

        $originalLine->update([
            'item_id' => $workLine->item_id,
            'barcode' => $workLine->barcode,
            'qty_primary' => $newQtyPrimary,
            'qty_secondary' => $newQtySecondary,
            'unit_price_primary' => $workLine->unit_price_primary,
            'unit_price_secondary' => $workLine->unit_price_secondary,
            'line_total' => $workLine->line_total,
            'profit' => 0,
        ]);

        if ($itemChanged || $qtyChanged || $priceChanged) {
            $profit = $inventory->applySalesLine(
                (int) $workLine->item_id,
                $warehouseId,
                $newQtyPrimary,
                $newQtySecondary,
                (float) $workLine->unit_price_primary,
                (int) $invoice->id,
                (int) $originalLine->id,
                movementDate: $work->invoice_date,
            );

            $originalLine->update(['profit' => $profit]);
        }
    }

    protected function createNewLine(
        SalesInvoice $invoice,
        SalesInvoiceWork $work,
        SalesInvoiceLineWork $workLine,
        SalesInventoryService $inventory,
        int $warehouseId,
    ): void {
        $salesLine = SalesInvoiceLine::query()->create([
            'sales_invoice_id' => $invoice->id,
            'item_id' => $workLine->item_id,
            'barcode' => $workLine->barcode,
            'qty_primary' => $workLine->qty_primary,
            'qty_secondary' => $workLine->qty_secondary,
            'unit_price_primary' => $workLine->unit_price_primary,
            'unit_price_secondary' => $workLine->unit_price_secondary,
            'line_total' => $workLine->line_total,
            'created_by' => Auth::id(),
        ]);

        $profit = $inventory->applySalesLine(
            (int) $workLine->item_id,
            $warehouseId,
            (float) $workLine->qty_primary,
            (float) $workLine->qty_secondary,
            (float) $workLine->unit_price_primary,
            (int) $invoice->id,
            (int) $salesLine->id,
            movementDate: $work->invoice_date,
        );

        $salesLine->update(['profit' => $profit]);
    }

    protected function syncEntryCustomerReceipt(
        SalesInvoice $invoice,
        SalesInvoiceWork $work,
        ?int $cashBoxId,
        ?int $bankAccountId,
    ): void {
        $receipt = CustomerReceipt::query()
            ->where('sales_invoice_id', $invoice->id)
            ->where('transaction_kind', 6)
            ->first();

        if ((float) $work->amount_paid <= 0) {
            if ($receipt) {
                $receipt->delete();
                app(CustomerReceiptService::class)->afterDeleted($receipt);
            }

            return;
        }

        $payload = [
            'receipt_date' => $work->invoice_date,
            'customer_id' => $work->customer_id,
            'payment_method_id' => $work->payment_method_id,
            'amount' => (float) $work->amount_paid,
            'warehouse_id' => $work->warehouse_id,
            'cash_box_id' => $cashBoxId,
            'bank_account_id' => $bankAccountId,
            'notes' => 'فاتورة مبيعات رقم '.(string) $invoice->id,
        ];

        if ($receipt) {
            $receipt->update($payload);
            app(CustomerReceiptService::class)->afterSaved($receipt);

            return;
        }

        CustomerReceipt::query()->create([
            ...$payload,
            'sales_invoice_id' => $invoice->id,
            'transaction_kind' => 6,
            'flow_direction' => 1,
            'created_by' => Auth::id(),
        ]);

        app(CustomerReceiptService::class)->syncSalesInvoice((int) $invoice->id);
    }
}
