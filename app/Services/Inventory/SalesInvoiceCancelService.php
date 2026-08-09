<?php

namespace App\Services\Inventory;

use App\Models\FifoAllocation;
use App\Models\SalesInvoice;
use App\Services\SystemOperationLogger;
use App\Support\SystemOperationContext;
use App\Support\SystemOperationType;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesInvoiceCancelService
{
    public function cancel(SalesInvoice $invoice): void
    {
        $invoice->assertCanBeDeleted();

        DB::connection($invoice->getConnectionName())->transaction(function () use ($invoice): void {
            $invoice->load('lines');

            $inventory = app(SalesInventoryService::class);
            $warehouseId = (int) $invoice->warehouse_id;
            $invoiceId = (int) $invoice->id;
            $customerId = $invoice->customer_id ? (int) $invoice->customer_id : null;

            foreach ($invoice->lines as $line) {
                if ($line->sales_return_id !== null) {
                    throw new RuntimeException('لا يمكن إلغاء فاتورة بها بنود مرجّعة');
                }

                $inventory->reverseSalesLine(
                    $line,
                    $warehouseId,
                    movementDate: $invoice->invoice_date,
                    notes: 'إلغاء فاتورة مبيعات رقم '.(string) $invoiceId,
                );

                $line->delete();
            }

            FifoAllocation::query()
                ->where('sales_invoice_id', $invoiceId)
                ->delete();

            $invoice->delete();

            SystemOperationLogger::cancelled(
                SystemOperationType::SALE,
                $invoiceId,
                SystemOperationContext::customer($customerId),
            );
        });
    }
}
