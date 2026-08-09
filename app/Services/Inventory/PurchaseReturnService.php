<?php

namespace App\Services\Inventory;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseReturn;
use App\Models\StockMovement;
use App\Models\WarehouseStock;
use App\Services\SystemOperationLogger;
use App\Support\SystemOperationContext;
use App\Support\SystemOperationType;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class PurchaseReturnService
{
    public function applyReturn(
        PurchaseInvoiceLine $line,
        float $returnQtyPrimary,
        float $returnQtySecondary,
        DateTimeInterface $returnDate,
    ): PurchaseReturn {
        if ($line->purchase_return_id !== null) {
            throw new RuntimeException('تم ترجيع هذا البند مسبقاً');
        }

        if ($returnQtyPrimary <= 0) {
            throw new RuntimeException('يجب ادخال كمية صحيحة');
        }

        $available = (float) $line->remaining_qty_primary;

        if ($returnQtyPrimary - 0.0001 > $available) {
            throw new RuntimeException('الكمية المرجعة أكبر من الكمية المتوفرة');
        }

        $invoice = $line->purchaseInvoice;

        if (! $invoice) {
            throw new RuntimeException('فاتورة الشراء غير موجودة');
        }

        $unitCost = (float) $line->unit_cost_primary;
        $returnTotal = round($returnQtyPrimary * $unitCost, 3);
        $newQty = (float) $line->qty_primary - $returnQtyPrimary;
        $newRemaining = $available - $returnQtyPrimary;
        $newLineTotal = round(max(0, (float) $line->line_cost_total - $returnTotal), 3);

        app(SalesInventoryService::class)->assertWarehouseStock(
            (int) $line->item_id,
            (int) $invoice->warehouse_id,
            $returnQtyPrimary,
        );

        $purchaseReturn = PurchaseReturn::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'purchase_invoice_line_id' => $line->id,
            'item_id' => $line->item_id,
            'return_date' => $returnDate,
            'qty_primary' => $returnQtyPrimary,
            'qty_secondary' => $returnQtySecondary,
            'unit_cost_primary' => $unitCost,
            'line_total' => $returnTotal,
            'created_by' => Auth::id(),
        ]);

        $this->decreaseWarehouseStock(
            (int) $invoice->warehouse_id,
            (int) $line->item_id,
            $returnQtyPrimary,
            $returnQtySecondary,
            PurchaseReturn::class,
            (int) $purchaseReturn->id,
            $returnDate,
            'ترجيع مشتريات — فاتورة رقم '.(string) $invoice->id,
        );

        $line->update([
            'qty_primary' => max(0, $newQty),
            'qty_secondary' => max(0, (float) $line->qty_secondary - $returnQtySecondary),
            'remaining_qty_primary' => max(0, $newRemaining),
            'remaining_qty_secondary' => max(0, (float) $line->remaining_qty_secondary - $returnQtySecondary),
            'line_cost_total' => $newLineTotal,
            'purchase_return_id' => $purchaseReturn->id,
        ]);

        return $purchaseReturn;
    }

    public function cancelReturn(PurchaseReturn $purchaseReturn): void
    {
        $line = $purchaseReturn->purchaseInvoiceLine;

        if (! $line) {
            throw new RuntimeException('بند الفاتورة غير موجود');
        }

        $invoice = $purchaseReturn->purchaseInvoice;

        if (! $invoice) {
            throw new RuntimeException('فاتورة الشراء غير موجودة');
        }

        $returnQty = (float) $purchaseReturn->qty_primary;
        $returnQtySecondary = (float) $purchaseReturn->qty_secondary;

        $line->update([
            'qty_primary' => (float) $line->qty_primary + $returnQty,
            'qty_secondary' => (float) $line->qty_secondary + $returnQtySecondary,
            'remaining_qty_primary' => (float) $line->remaining_qty_primary + $returnQty,
            'remaining_qty_secondary' => (float) $line->remaining_qty_secondary + $returnQtySecondary,
            'line_cost_total' => round((float) $line->line_cost_total + (float) $purchaseReturn->line_total, 3),
            'purchase_return_id' => null,
        ]);

        app(PurchaseInventoryService::class)->applyPurchaseLine(
            (int) $line->item_id,
            (int) $invoice->warehouse_id,
            $returnQty,
            $returnQtySecondary,
            (int) $invoice->payment_method_id,
            (float) $purchaseReturn->unit_cost_primary,
            PurchaseReturn::class,
            (int) $purchaseReturn->id,
            movementDate: $purchaseReturn->return_date,
            notes: 'إلغاء ترجيع مشتريات — فاتورة رقم '.(string) $invoice->id,
        );

        $invoiceId = $invoice->id;
        $context = SystemOperationContext::item(
            $purchaseReturn->item_id ? (int) $purchaseReturn->item_id : null,
        );

        $purchaseReturn->delete();

        SystemOperationLogger::cancelled(SystemOperationType::PURCHASE_RETURN, $invoiceId, $context);
    }

    protected function decreaseWarehouseStock(
        int $warehouseId,
        int $itemId,
        float $qtyPrimary,
        float $qtySecondary,
        string $referenceType,
        int $referenceId,
        DateTimeInterface $movementDate,
        string $notes,
    ): void {
        $stock = WarehouseStock::query()->firstOrNew([
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
        ]);

        $stock->quantity_primary = max(0, (float) $stock->quantity_primary - $qtyPrimary);
        $stock->quantity_secondary = max(0, (float) $stock->quantity_secondary - $qtySecondary);
        $stock->save();

        StockMovement::query()->create([
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'movement_type' => 'purchase_return',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'qty_primary' => -abs($qtyPrimary),
            'qty_secondary' => -abs($qtySecondary),
            'unit_cost' => null,
            'notes' => $notes,
            'movement_date' => $movementDate,
            'created_by' => Auth::id(),
        ]);
    }
}
