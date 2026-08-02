<?php

namespace App\Services\Inventory;

use App\Models\FifoAllocation;
use App\Models\PurchaseInvoiceLine;
use App\Models\SalesInvoiceLine;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\WarehouseStock;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class SalesReturnService
{
    public function applyReturn(
        SalesInvoiceLine $line,
        float $returnQtyPrimary,
        float $returnQtySecondary,
        DateTimeInterface $returnDate,
    ): SalesReturn {
        if ($line->sales_return_id !== null) {
            throw new RuntimeException('تم ترجيع هذا البند مسبقاً');
        }

        if ($returnQtyPrimary <= 0) {
            throw new RuntimeException('يجب ادخال كمية صحيحة');
        }

        if ($returnQtyPrimary - 0.0001 > (float) $line->qty_primary) {
            throw new RuntimeException('الكمية المرجعة أكبر من كمية البند');
        }

        $invoice = $line->salesInvoice;

        if (! $invoice) {
            throw new RuntimeException('فاتورة البيع غير موجودة');
        }

        $unitPrice = (float) $line->unit_price_primary;
        $returnTotal = round($returnQtyPrimary * $unitPrice, 3);
        $oldQty = (float) $line->qty_primary;
        $oldProfit = (float) $line->profit;
        $newQty = $oldQty - $returnQtyPrimary;
        $newLineTotal = round(max(0, (float) $line->line_total - $returnTotal), 3);
        $newProfit = $oldQty > 0 ? round($oldProfit * ($newQty / $oldQty), 3) : 0;

        $this->reverseFifoAllocations($line, $returnQtyPrimary);

        $salesReturn = SalesReturn::query()->create([
            'sales_invoice_id' => $invoice->id,
            'sales_invoice_line_id' => $line->id,
            'item_id' => $line->item_id,
            'return_date' => $returnDate,
            'qty_primary' => $returnQtyPrimary,
            'qty_secondary' => $returnQtySecondary,
            'unit_price_primary' => $unitPrice,
            'line_total' => $returnTotal,
            'created_by' => Auth::id(),
        ]);

        $this->increaseWarehouseStock(
            (int) $invoice->warehouse_id,
            (int) $line->item_id,
            $returnQtyPrimary,
            $returnQtySecondary,
            SalesReturn::class,
            (int) $salesReturn->id,
            $returnDate,
            'ترجيع مبيعات — فاتورة رقم '.(string) $invoice->id,
        );

        $line->update([
            'qty_primary' => $newQty,
            'qty_secondary' => max(0, (float) $line->qty_secondary - $returnQtySecondary),
            'line_total' => $newLineTotal,
            'profit' => $newProfit,
            'sales_return_id' => $salesReturn->id,
        ]);

        return $salesReturn;
    }

    public function cancelReturn(SalesReturn $salesReturn): void
    {
        $line = $salesReturn->salesInvoiceLine;

        if (! $line) {
            throw new RuntimeException('بند الفاتورة غير موجود');
        }

        $invoice = $salesReturn->salesInvoice;

        if (! $invoice) {
            throw new RuntimeException('فاتورة البيع غير موجودة');
        }

        $returnQty = (float) $salesReturn->qty_primary;
        $returnQtySecondary = (float) $salesReturn->qty_secondary;

        $line->update([
            'qty_primary' => (float) $line->qty_primary + $returnQty,
            'qty_secondary' => (float) $line->qty_secondary + $returnQtySecondary,
            'line_total' => round((float) $line->line_total + (float) $salesReturn->line_total, 3),
            'sales_return_id' => null,
        ]);

        $line->refresh();

        $profitDelta = app(SalesInventoryService::class)->applySalesLine(
            (int) $line->item_id,
            (int) $invoice->warehouse_id,
            $returnQty,
            $returnQtySecondary,
            (float) $line->unit_price_primary,
            (int) $invoice->id,
            (int) $line->id,
            movementDate: $salesReturn->return_date,
        );

        $line->update([
            'profit' => round((float) $line->profit + $profitDelta, 3),
        ]);

        $salesReturn->delete();
    }

    protected function reverseFifoAllocations(SalesInvoiceLine $line, float $returnQtyPrimary): void
    {
        $remaining = $returnQtyPrimary;

        $allocations = FifoAllocation::query()
            ->where('sales_invoice_line_id', $line->id)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($allocations as $allocation) {
            if ($remaining <= 0.0001) {
                break;
            }

            $allocated = (float) $allocation->qty_primary;
            $take = min($allocated, $remaining);

            if ($allocation->purchase_invoice_line_id) {
                PurchaseInvoiceLine::query()
                    ->whereKey($allocation->purchase_invoice_line_id)
                    ->lockForUpdate()
                    ->first()
                    ?->increment('remaining_qty_primary', $take);
            }

            if ($take + 0.0001 >= $allocated) {
                $allocation->delete();
            } else {
                $allocation->update([
                    'qty_primary' => $allocated - $take,
                ]);
            }

            $remaining -= $take;
        }

        if ($remaining > 0.0001) {
            throw new RuntimeException('تعذر عكس تخصيص FIFO للصنف '.$line->item_id);
        }
    }

    protected function increaseWarehouseStock(
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

        $stock->quantity_primary = (float) $stock->quantity_primary + $qtyPrimary;
        $stock->quantity_secondary = (float) $stock->quantity_secondary + $qtySecondary;
        $stock->save();

        StockMovement::query()->create([
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'movement_type' => 'sales_return',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'qty_primary' => abs($qtyPrimary),
            'qty_secondary' => abs($qtySecondary),
            'unit_cost' => null,
            'notes' => $notes,
            'movement_date' => $movementDate,
            'created_by' => Auth::id(),
        ]);
    }
}
