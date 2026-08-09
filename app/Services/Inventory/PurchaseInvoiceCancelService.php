<?php

namespace App\Services\Inventory;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Services\SystemOperationLogger;
use App\Support\SystemOperationType;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseInvoiceCancelService
{
    public function cancel(PurchaseInvoice $invoice): void
    {
        $invoice->assertCanBeDeleted();

        DB::connection($invoice->getConnectionName())->transaction(function () use ($invoice): void {
            $invoice->load('lines');

            $inventory = app(PurchaseInventoryService::class);
            $warehouseId = (int) $invoice->warehouse_id;
            $invoiceId = (int) $invoice->id;

            foreach ($invoice->lines as $line) {
                $this->assertLineCanBeCancelled($line);

                $inventory->reversePurchaseLine(
                    (int) $line->item_id,
                    $warehouseId,
                    (float) $line->qty_primary,
                    (float) $line->qty_secondary,
                    referenceType: PurchaseInvoice::class,
                    referenceId: $invoiceId,
                    movementDate: $invoice->invoice_date,
                    notes: 'إلغاء فاتورة مشتريات رقم '.(string) $invoiceId,
                );

                $line->delete();
            }

            $invoice->delete();

            SystemOperationLogger::cancelled(SystemOperationType::PURCHASE, $invoiceId);
        });
    }

    protected function assertLineCanBeCancelled(PurchaseInvoiceLine $line): void
    {
        if ($line->purchase_return_id !== null) {
            throw new RuntimeException('لا يمكن إلغاء فاتورة بها بنود مرجّعة');
        }

        $remaining = (float) $line->remaining_qty_primary;
        $qty = (float) $line->qty_primary;

        if ($remaining + 0.0001 < $qty) {
            throw new RuntimeException('لا يمكن إلغاء الفاتورة .. رصيد أحد الأصناف مستهلك من مبيعات');
        }
    }
}
