<?php

namespace App\Services\Inventory;

use App\Models\FifoAllocation;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\StockMovement;
use App\Models\WarehouseStock;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SalesInventoryService
{
    private const FIFO_ADJUSTMENT_NOTES = 'تسوية FIFO تلقائية';

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

    public function ensureFifoLayersForSale(int $itemId, int $warehouseId, float $qtyPrimary): void
    {
        $availableFifo = $this->totalFifoRemaining($itemId);

        if ($availableFifo + 0.0001 >= $qtyPrimary) {
            return;
        }

        $this->replenishFifoShortfall($itemId, $warehouseId, $qtyPrimary - $availableFifo);
    }

    public function restoreMissingFifoQty(int $itemId, int $warehouseId, float $qtyPrimary): void
    {
        $this->replenishFifoShortfall($itemId, $warehouseId, $qtyPrimary);
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
        $this->ensureFifoLayersForSale($itemId, $warehouseId, $qtyPrimary);

        $remainingToAllocate = $qtyPrimary;
        $profit = 0.0;

        $layers = $this->fifoLayersForSale($itemId, $warehouseId);

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
            $this->replenishFifoShortfall($itemId, $warehouseId, $remainingToAllocate);

            foreach ($this->fifoLayersForSale($itemId, $warehouseId) as $layer) {
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
        }

        if ($remainingToAllocate > 0.0001) {
            Log::error('FIFO allocation still short after reconciliation', [
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'shortfall' => $remainingToAllocate,
                'sales_invoice_id' => $salesInvoiceId,
                'sales_invoice_line_id' => $salesInvoiceLineId,
            ]);

            throw new RuntimeException('حدث خطأ !! يرجي التواصل مع المبرمج');
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

    public function reverseSalesLine(
        SalesInvoiceLine $line,
        int $warehouseId,
        ?DateTimeInterface $movementDate = null,
        ?string $notes = null,
    ): void {
        if ($line->sales_return_id !== null) {
            throw new RuntimeException('لا يمكن تعديل بند تم ترجيعه');
        }

        $qtyPrimary = (float) $line->qty_primary;
        $qtySecondary = (float) $line->qty_secondary;

        if ($qtyPrimary <= 0.0001) {
            return;
        }

        $this->reverseFifoAllocationsForLine($line, $warehouseId, $qtyPrimary);

        $stock = WarehouseStock::query()->firstOrNew([
            'warehouse_id' => $warehouseId,
            'item_id' => $line->item_id,
        ]);

        $stock->quantity_primary = (float) $stock->quantity_primary + $qtyPrimary;
        $stock->quantity_secondary = (float) $stock->quantity_secondary + $qtySecondary;
        $stock->save();

        StockMovement::query()->create([
            'warehouse_id' => $warehouseId,
            'item_id' => $line->item_id,
            'movement_type' => 'sale_reversal',
            'reference_type' => SalesInvoice::class,
            'reference_id' => $line->sales_invoice_id,
            'qty_primary' => abs($qtyPrimary),
            'qty_secondary' => abs($qtySecondary),
            'unit_cost' => (float) $line->unit_price_primary,
            'notes' => $notes ?? 'عكس بند فاتورة مبيعات رقم '.(string) $line->sales_invoice_id,
            'movement_date' => $movementDate ?? now(),
            'created_by' => Auth::id(),
        ]);
    }

    protected function reverseFifoAllocationsForLine(
        SalesInvoiceLine $line,
        int $warehouseId,
        float $returnQtyPrimary,
    ): void {
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
            $this->replenishFifoShortfall((int) $line->item_id, $warehouseId, $remaining);

            Log::info('Restored FIFO layers after sales reversal without allocations', [
                'item_id' => $line->item_id,
                'warehouse_id' => $warehouseId,
                'qty_primary' => $remaining,
                'sales_invoice_line_id' => $line->id,
            ]);
        }
    }

    protected function replenishFifoShortfall(int $itemId, int $warehouseId, float $shortfall): void
    {
        if ($shortfall <= 0.0001) {
            return;
        }

        $remaining = $shortfall;

        foreach ([$warehouseId, null] as $preferredWarehouseId) {
            if ($remaining <= 0.0001) {
                break;
            }

            $remaining = $this->restoreDepletedPurchaseLines($itemId, $preferredWarehouseId, $remaining);
        }

        if ($remaining > 0.0001) {
            $this->createFifoAdjustmentLine($itemId, $warehouseId, $remaining);

            Log::info('Created automatic FIFO adjustment layer', [
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'qty_primary' => $remaining,
            ]);
        }
    }

    protected function restoreDepletedPurchaseLines(
        int $itemId,
        ?int $warehouseId,
        float $qtyToRestore,
    ): float {
        $remaining = $qtyToRestore;

        $query = PurchaseInvoiceLine::query()
            ->where('item_id', $itemId)
            ->whereColumn('remaining_qty_primary', '<', 'qty_primary')
            ->whereHas('purchaseInvoice')
            ->with('purchaseInvoice:id,warehouse_id')
            ->lockForUpdate();

        if ($warehouseId !== null) {
            $query->whereHas('purchaseInvoice', fn ($builder) => $builder->where('warehouse_id', $warehouseId));
        }

        /** @var Collection<int, PurchaseInvoiceLine> $lines */
        $lines = $query->get()->sortByDesc(fn (PurchaseInvoiceLine $line): int => (int) $line->id);

        foreach ($lines as $line) {
            if ($remaining <= 0.0001) {
                break;
            }

            $consumed = (float) $line->qty_primary - (float) $line->remaining_qty_primary;

            if ($consumed <= 0.0001) {
                continue;
            }

            $take = min($consumed, $remaining);

            $line->update([
                'remaining_qty_primary' => (float) $line->remaining_qty_primary + $take,
            ]);

            $remaining -= $take;
        }

        return $remaining;
    }

    protected function createFifoAdjustmentLine(int $itemId, int $warehouseId, float $qtyPrimary): PurchaseInvoiceLine
    {
        $item = Item::query()->find($itemId);
        $unitCost = $this->resolveUnitCostForItem($itemId);

        $invoice = PurchaseInvoice::query()
            ->where('warehouse_id', $warehouseId)
            ->where('notes', self::FIFO_ADJUSTMENT_NOTES)
            ->whereDate('invoice_date', today())
            ->first();

        if (! $invoice) {
            $invoice = PurchaseInvoice::query()->create([
                'invoice_date' => today(),
                'supplier_id' => null,
                'payment_method_id' => 1,
                'warehouse_id' => $warehouseId,
                'lines_subtotal' => 0,
                'amount_paid' => 0,
                'balance' => 0,
                'notes' => self::FIFO_ADJUSTMENT_NOTES,
                'created_by' => Auth::id(),
            ]);
        }

        $line = PurchaseInvoiceLine::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'item_id' => $itemId,
            'barcode' => $item?->barcode,
            'qty_primary' => $qtyPrimary,
            'qty_secondary' => 0,
            'unit_cost_primary' => $unitCost,
            'line_cost_total' => round($qtyPrimary * $unitCost, 3),
            'remaining_qty_primary' => $qtyPrimary,
            'remaining_qty_secondary' => 0,
            'created_by' => Auth::id(),
        ]);

        $invoice->recalculateTotals();

        return $line;
    }

    protected function resolveUnitCostForItem(int $itemId): float
    {
        $lastCost = PurchaseInvoiceLine::query()
            ->where('item_id', $itemId)
            ->where('unit_cost_primary', '>', 0)
            ->orderByDesc('id')
            ->value('unit_cost_primary');

        if ($lastCost !== null && (float) $lastCost > 0) {
            return (float) $lastCost;
        }

        return Item::resolveBuyPrice($itemId, 1);
    }

    protected function totalFifoRemaining(int $itemId): float
    {
        return (float) PurchaseInvoiceLine::query()
            ->where('item_id', $itemId)
            ->where('remaining_qty_primary', '>', 0)
            ->sum('remaining_qty_primary');
    }

    /**
     * Prefer purchase layers in the sale warehouse, then fall back company-wide.
     *
     * @return Collection<int, PurchaseInvoiceLine>
     */
    protected function fifoLayersForSale(int $itemId, int $warehouseId): Collection
    {
        return PurchaseInvoiceLine::query()
            ->where('item_id', $itemId)
            ->where('remaining_qty_primary', '>', 0)
            ->whereHas('purchaseInvoice')
            ->with('purchaseInvoice:id,warehouse_id,invoice_date')
            ->lockForUpdate()
            ->get()
            ->sortBy([
                fn (PurchaseInvoiceLine $line): int => (int) $line->purchaseInvoice?->warehouse_id === $warehouseId ? 0 : 1,
                fn (PurchaseInvoiceLine $line) => (string) ($line->purchaseInvoice?->invoice_date ?? ''),
                fn (PurchaseInvoiceLine $line): int => (int) $line->id,
            ])
            ->values();
    }
}
