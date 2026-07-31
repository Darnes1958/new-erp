<?php

namespace App\Services\Inventory;

use App\Models\FifoAllocation;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use App\Models\WarehouseStock;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class SalesInventoryService
{
    public function warehouseStockQty(int $itemId, int $warehouseId): float
    {
        return (float) (WarehouseStock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->value('quantity_primary') ?? 0);
    }

    public function assertWarehouseStock(int $itemId, int $warehouseId, float $qtyPrimary): void
    {
        $available = $this->warehouseStockQty($itemId, $warehouseId);

        if ($available + 0.0001 < $qtyPrimary) {
            throw new RuntimeException('الرصيد لا يسمح');
        }
    }

    public function applySalesLine(
        int $itemId,
        int $warehouseId,
        float $qtyPrimary,
        float $qtySecondary,
        float $unitSellPricePrimary,
        int $salesInvoiceId,
        int $salesInvoiceLineId,
        ?DateTimeInterface $movementDate = null,
    ): float {
        $this->assertWarehouseStock($itemId, $warehouseId, $qtyPrimary);

        $remainingToAllocate = $qtyPrimary;
        $profit = 0.0;

        $layers = PurchaseInvoiceLine::query()
            ->where('item_id', $itemId)
            ->where('remaining_qty_primary', '>', 0)
            ->whereHas('purchaseInvoice', fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if ($remainingToAllocate <= 0.0001) {
                break;
            }

            $available = (float) $layer->remaining_qty_primary;
            $take = min($available, $remainingToAllocate);
            $unitCost = (float) $layer->unit_cost_primary;

            $layer->update([
                'remaining_qty_primary' => $available - $take,
            ]);

            FifoAllocation::query()->create([
                'purchase_invoice_id' => $layer->purchase_invoice_id,
                'purchase_invoice_line_id' => $layer->id,
                'sales_invoice_id' => $salesInvoiceId,
                'sales_invoice_line_id' => $salesInvoiceLineId,
                'item_id' => $itemId,
                'qty_primary' => $take,
                'qty_secondary' => 0,
                'unit_cost' => $unitCost,
            ]);

            $profit += ($unitSellPricePrimary - $unitCost) * $take;
            $remainingToAllocate -= $take;
        }

        if ($remainingToAllocate > 0.0001) {
            throw new RuntimeException('رصيد FIFO للصنف غير كافٍ');
        }

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
            'movement_type' => 'sale',
            'reference_type' => SalesInvoice::class,
            'reference_id' => $salesInvoiceId,
            'qty_primary' => -abs($qtyPrimary),
            'qty_secondary' => -abs($qtySecondary),
            'unit_cost' => $unitSellPricePrimary,
            'notes' => 'فاتورة مبيعات رقم '.(string) $salesInvoiceId,
            'movement_date' => $movementDate ?? now(),
            'created_by' => Auth::id(),
        ]);

        return $profit;
    }
}
