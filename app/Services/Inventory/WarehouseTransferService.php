<?php

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\StockMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferLayer;
use App\Models\WarehouseTransferLine;
use App\Services\SystemOperationLogger;
use App\Support\SystemOperationType;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WarehouseTransferService
{
    /**
     * @param  array<int, array{item_id: int, qty_primary: float, qty_secondary?: float}>  $lines
     */
    public function store(
        int $warehouseFromId,
        int $warehouseToId,
        DateTimeInterface $transferDate,
        array $lines,
    ): WarehouseTransfer {
        if ($warehouseFromId === $warehouseToId) {
            throw new RuntimeException('لا يمكن النقل إلى نفس المكان');
        }

        if ($lines === []) {
            throw new RuntimeException('لم يتم ادخال اصناف');
        }

        return DB::transaction(function () use ($warehouseFromId, $warehouseToId, $transferDate, $lines): WarehouseTransfer {
            $salesInventory = app(SalesInventoryService::class);

            foreach ($lines as $line) {
                $qtyPrimary = (float) ($line['qty_primary'] ?? 0);

                if ($qtyPrimary <= 0) {
                    throw new RuntimeException('يجب ادخال كمية صحيحة');
                }

                $salesInventory->assertWarehouseStock(
                    (int) $line['item_id'],
                    $warehouseFromId,
                    $qtyPrimary,
                );
            }

            $transfer = WarehouseTransfer::query()->create([
                'transfer_date' => $transferDate,
                'warehouse_from_id' => $warehouseFromId,
                'warehouse_to_id' => $warehouseToId,
                'created_by' => Auth::id(),
            ]);

            $destinationInvoice = PurchaseInvoice::query()->create([
                'invoice_date' => $transferDate,
                'supplier_id' => null,
                'payment_method_id' => 1,
                'warehouse_id' => $warehouseToId,
                'lines_subtotal' => 0,
                'amount_paid' => 0,
                'balance' => 0,
                'notes' => 'نقل داخلي — إذن رقم '.(string) $transfer->id,
                'created_by' => Auth::id(),
            ]);

            $transfer->update([
                'destination_purchase_invoice_id' => $destinationInvoice->id,
            ]);

            foreach ($lines as $lineData) {
                $this->applyLine(
                    $transfer,
                    $destinationInvoice,
                    (int) $lineData['item_id'],
                    (float) $lineData['qty_primary'],
                    (float) ($lineData['qty_secondary'] ?? 0),
                    $transferDate,
                );
            }

            $destinationInvoice->recalculateTotals();

            return $transfer->fresh(['lines.item', 'warehouseFrom', 'warehouseTo']);
        });
    }

    public function reverse(WarehouseTransfer $transfer): void
    {
        $transfer->load(['lines.layers', 'warehouseFrom', 'warehouseTo']);
        $transferId = (int) $transfer->id;
        $lines = $transfer->lines;

        DB::transaction(function () use ($transfer): void {
            $salesInventory = app(SalesInventoryService::class);

            foreach ($transfer->lines as $line) {
                $salesInventory->assertWarehouseStock(
                    (int) $line->item_id,
                    (int) $transfer->warehouse_to_id,
                    (float) $line->qty_primary,
                );
            }

            foreach ($transfer->lines as $line) {
                foreach ($line->layers as $layer) {
                    $this->reverseLayer($layer);
                }

                $this->adjustWarehouseStock(
                    (int) $transfer->warehouse_from_id,
                    (int) $line->item_id,
                    (float) $line->qty_primary,
                    (float) $line->qty_secondary,
                );

                $this->adjustWarehouseStock(
                    (int) $transfer->warehouse_to_id,
                    (int) $line->item_id,
                    -(float) $line->qty_primary,
                    -(float) $line->qty_secondary,
                );

                $this->recordStockMovement(
                    (int) $transfer->warehouse_from_id,
                    (int) $line->item_id,
                    (float) $line->qty_primary,
                    (float) $line->qty_secondary,
                    'transfer_reversal_in',
                    WarehouseTransfer::class,
                    (int) $transfer->id,
                    $transfer->transfer_date,
                    'إلغاء نقل — إذن رقم '.(string) $transfer->id,
                );

                $this->recordStockMovement(
                    (int) $transfer->warehouse_to_id,
                    (int) $line->item_id,
                    -(float) $line->qty_primary,
                    -(float) $line->qty_secondary,
                    'transfer_reversal_out',
                    WarehouseTransfer::class,
                    (int) $transfer->id,
                    $transfer->transfer_date,
                    'إلغاء نقل — إذن رقم '.(string) $transfer->id,
                );
            }

            if ($transfer->destination_purchase_invoice_id) {
                PurchaseInvoiceLine::query()
                    ->where('purchase_invoice_id', $transfer->destination_purchase_invoice_id)
                    ->delete();

                PurchaseInvoice::query()
                    ->whereKey($transfer->destination_purchase_invoice_id)
                    ->delete();
            }

            $transfer->delete();
        });

        foreach ($lines as $line) {
            SystemOperationLogger::cancelled(
                SystemOperationType::WAREHOUSE_TRANSFER,
                $transferId,
                SystemOperationContext::item((int) $line->item_id),
            );
        }
    }

    private function applyLine(
        WarehouseTransfer $transfer,
        PurchaseInvoice $destinationInvoice,
        int $itemId,
        float $qtyPrimary,
        float $qtySecondary,
        DateTimeInterface $transferDate,
    ): void {
        $transferLine = WarehouseTransferLine::query()->create([
            'warehouse_transfer_id' => $transfer->id,
            'item_id' => $itemId,
            'qty_primary' => $qtyPrimary,
            'qty_secondary' => $qtySecondary,
            'created_by' => Auth::id(),
        ]);

        $allocations = $this->consumeFifoLayers($itemId, (int) $transfer->warehouse_from_id, $qtyPrimary);
        $item = Item::query()->find($itemId);

        foreach ($allocations as $allocation) {
            $destinationLine = PurchaseInvoiceLine::query()->create([
                'purchase_invoice_id' => $destinationInvoice->id,
                'item_id' => $itemId,
                'barcode' => $item?->barcode,
                'qty_primary' => $allocation['qty_primary'],
                'qty_secondary' => 0,
                'unit_cost_primary' => $allocation['unit_cost'],
                'line_cost_total' => round($allocation['qty_primary'] * $allocation['unit_cost'], 3),
                'remaining_qty_primary' => $allocation['qty_primary'],
                'remaining_qty_secondary' => 0,
                'created_by' => Auth::id(),
            ]);

            WarehouseTransferLayer::query()->create([
                'warehouse_transfer_line_id' => $transferLine->id,
                'source_purchase_invoice_line_id' => $allocation['source_purchase_invoice_line_id'],
                'destination_purchase_invoice_line_id' => $destinationLine->id,
                'qty_primary' => $allocation['qty_primary'],
                'unit_cost' => $allocation['unit_cost'],
            ]);
        }

        $this->adjustWarehouseStock(
            (int) $transfer->warehouse_from_id,
            $itemId,
            -$qtyPrimary,
            -$qtySecondary,
        );

        $this->adjustWarehouseStock(
            (int) $transfer->warehouse_to_id,
            $itemId,
            $qtyPrimary,
            $qtySecondary,
        );

        $this->recordStockMovement(
            (int) $transfer->warehouse_from_id,
            $itemId,
            -$qtyPrimary,
            -$qtySecondary,
            'transfer_out',
            WarehouseTransfer::class,
            (int) $transfer->id,
            $transferDate,
            'نقل إلى '.($transfer->warehouseTo?->name ?? ''),
        );

        $this->recordStockMovement(
            (int) $transfer->warehouse_to_id,
            $itemId,
            $qtyPrimary,
            $qtySecondary,
            'transfer_in',
            WarehouseTransfer::class,
            (int) $transfer->id,
            $transferDate,
            'نقل من '.($transfer->warehouseFrom?->name ?? ''),
        );
    }

    /**
     * @return array<int, array{source_purchase_invoice_line_id: int, qty_primary: float, unit_cost: float}>
     */
    private function consumeFifoLayers(int $itemId, int $warehouseId, float $qtyPrimary): array
    {
        $salesInventory = app(SalesInventoryService::class);
        $salesInventory->ensureFifoLayersForSale($itemId, $warehouseId, $qtyPrimary);

        $remainingToAllocate = $qtyPrimary;
        $allocations = [];

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

            $layer->update([
                'remaining_qty_primary' => $available - $take,
            ]);

            $allocations[] = [
                'source_purchase_invoice_line_id' => (int) $layer->id,
                'qty_primary' => $take,
                'unit_cost' => (float) $layer->unit_cost_primary,
            ];

            $remainingToAllocate -= $take;
        }

        if ($remainingToAllocate > 0.0001) {
            $salesInventory->ensureFifoLayersForSale($itemId, $warehouseId, $qtyPrimary);

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

                $layer->update([
                    'remaining_qty_primary' => $available - $take,
                ]);

                $allocations[] = [
                    'source_purchase_invoice_line_id' => (int) $layer->id,
                    'qty_primary' => $take,
                    'unit_cost' => (float) $layer->unit_cost_primary,
                ];

                $remainingToAllocate -= $take;
            }
        }

        if ($remainingToAllocate > 0.0001) {
            throw new RuntimeException('حدث خطأ !! يرجي التواصل مع المبرمج');
        }

        return $allocations;
    }

    private function reverseLayer(WarehouseTransferLayer $layer): void
    {
        PurchaseInvoiceLine::query()
            ->whereKey($layer->source_purchase_invoice_line_id)
            ->increment('remaining_qty_primary', (float) $layer->qty_primary);

        $destinationLine = PurchaseInvoiceLine::query()
            ->whereKey($layer->destination_purchase_invoice_line_id)
            ->lockForUpdate()
            ->first();

        if (! $destinationLine) {
            return;
        }

        $newRemaining = (float) $destinationLine->remaining_qty_primary - (float) $layer->qty_primary;

        if ($newRemaining < -0.0001) {
            throw new RuntimeException('يوجد صنف أو أصناف رصيدها لا يسمح');
        }

        if (abs($newRemaining) <= 0.0001) {
            $destinationLine->delete();

            return;
        }

        $destinationLine->update([
            'remaining_qty_primary' => $newRemaining,
            'qty_primary' => max(0, (float) $destinationLine->qty_primary - (float) $layer->qty_primary),
            'line_cost_total' => round($newRemaining * (float) $destinationLine->unit_cost_primary, 3),
        ]);
    }

    private function adjustWarehouseStock(
        int $warehouseId,
        int $itemId,
        float $deltaPrimary,
        float $deltaSecondary,
    ): void {
        $stock = WarehouseStock::query()->firstOrNew([
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
        ]);

        $stock->quantity_primary = max(0, (float) $stock->quantity_primary + $deltaPrimary);
        $stock->quantity_secondary = max(0, (float) $stock->quantity_secondary + $deltaSecondary);
        $stock->save();
    }

    private function recordStockMovement(
        int $warehouseId,
        int $itemId,
        float $qtyPrimary,
        float $qtySecondary,
        string $movementType,
        string $referenceType,
        int $referenceId,
        DateTimeInterface $movementDate,
        ?string $notes = null,
    ): void {
        StockMovement::query()->create([
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'movement_type' => $movementType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'qty_primary' => $qtyPrimary,
            'qty_secondary' => $qtySecondary,
            'unit_cost' => null,
            'notes' => $notes,
            'movement_date' => $movementDate,
            'created_by' => Auth::id(),
        ]);
    }

    public function warehouseStockQty(int $itemId, int $warehouseId): float
    {
        return app(SalesInventoryService::class)->warehouseStockQty($itemId, $warehouseId);
    }
}
