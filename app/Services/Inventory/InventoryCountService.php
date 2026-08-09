<?php

namespace App\Services\Inventory;

use App\Models\InventoryCountLine;
use App\Models\InventoryCountSession;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\StockMovement;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryCountService
{
    public function __construct(
        protected SalesInventoryService $salesInventory,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareLineAttributes(array $data): array
    {
        $sessionId = (int) ($data['inventory_count_session_id'] ?? InventoryCountSession::activeSessionId());
        $warehouseId = (int) $data['warehouse_id'];
        $itemId = (int) $data['item_id'];
        $actualBalance = (float) $data['actual_balance'];

        if ($sessionId <= 0) {
            throw new RuntimeException('لا توجد جلسة جرد نشطة');
        }

        $bookBalance = $this->salesInventory->warehouseStockQty($itemId, $warehouseId);
        $difference = round($actualBalance - $bookBalance, 3);
        $unitCost = (float) Item::query()->whereKey($itemId)->value('default_buy_price');

        $data['inventory_count_session_id'] = $sessionId;
        $data['book_balance'] = $bookBalance;
        $data['quantity_difference'] = $difference;
        $data['value_amount'] = round($difference * $unitCost, 3);
        $data['created_by'] = Auth::id();

        return $data;
    }

    public function applyLine(InventoryCountLine $line): void
    {
        DB::transaction(function () use ($line): void {
            $difference = (float) $line->quantity_difference;

            if (abs($difference) <= 0.0001) {
                $this->setWarehouseStock(
                    $line->item_id,
                    $line->warehouse_id,
                    (float) $line->actual_balance,
                );

                $this->recordMovement($line, 0.0);

                return;
            }

            $unitCost = abs($difference) > 0.0001
                ? round((float) $line->value_amount / $difference, 3)
                : (float) Item::query()->whereKey($line->item_id)->value('default_buy_price');

            $this->setWarehouseStock(
                $line->item_id,
                $line->warehouse_id,
                (float) $line->actual_balance,
            );

            $fifoLine = $this->adjustFifo($line, $difference, $unitCost);

            if ($fifoLine) {
                $line->forceFill([
                    'fifo_purchase_invoice_line_id' => $fifoLine->id,
                ])->saveQuietly();
            }

            $this->recordMovement($line, $difference);
        });
    }

    public function reverseLine(InventoryCountLine $line): void
    {
        if (! $this->canDeleteLine($line)) {
            throw new RuntimeException('لا يمكن حذف الجرد لأن الرصيد الحالي تغيّر');
        }

        DB::transaction(function () use ($line): void {
            $difference = (float) $line->quantity_difference;

            $this->setWarehouseStock(
                $line->item_id,
                $line->warehouse_id,
                (float) $line->book_balance,
            );

            if ($difference > 0.0001) {
                $this->reverseSurplusFifo($line);
            } elseif ($difference < -0.0001) {
                $unitCost = round((float) $line->value_amount / $difference, 3);
                $this->restoreDeficitFifo($line, abs($difference), $unitCost);
            }

            StockMovement::query()
                ->where('movement_type', 'inventory_count')
                ->where('reference_type', InventoryCountLine::class)
                ->where('reference_id', $line->id)
                ->delete();
        });
    }

    public function canDeleteLine(InventoryCountLine $line): bool
    {
        $current = $this->salesInventory->warehouseStockQty(
            (int) $line->item_id,
            (int) $line->warehouse_id,
        );

        return abs($current - (float) $line->actual_balance) <= 0.0001;
    }

    protected function setWarehouseStock(int $itemId, int $warehouseId, float $quantityPrimary): void
    {
        $stock = WarehouseStock::query()->firstOrNew([
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
        ]);

        $stock->quantity_primary = max(0, $quantityPrimary);
        $stock->save();
    }

    protected function adjustFifo(InventoryCountLine $countLine, float $difference, float $unitCost): ?PurchaseInvoiceLine
    {
        if ($difference > 0.0001) {
            $layer = PurchaseInvoiceLine::query()
                ->where('item_id', $countLine->item_id)
                ->whereHas('purchaseInvoice', fn ($query) => $query->where('warehouse_id', $countLine->warehouse_id))
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($layer) {
                $layer->increment('remaining_qty_primary', $difference);

                $countLine->forceFill([
                    'fifo_layer_created' => false,
                ])->saveQuietly();

                return $layer->fresh();
            }

            $createdLayer = $this->createSurplusPurchaseLayer($countLine, $difference, $unitCost);

            $countLine->forceFill([
                'fifo_layer_created' => true,
            ])->saveQuietly();

            return $createdLayer;
        }

        if ($difference < -0.0001) {
            $this->consumeFifoLayers(
                (int) $countLine->item_id,
                (int) $countLine->warehouse_id,
                abs($difference),
            );
        }

        return null;
    }

    protected function reverseSurplusFifo(InventoryCountLine $countLine): void
    {
        if (! $countLine->fifo_purchase_invoice_line_id) {
            return;
        }

        $layer = PurchaseInvoiceLine::query()
            ->lockForUpdate()
            ->find($countLine->fifo_purchase_invoice_line_id);

        if (! $layer) {
            return;
        }

        $difference = (float) $countLine->quantity_difference;

        if ($countLine->fifo_layer_created) {
            $remaining = (float) $layer->remaining_qty_primary;

            if ($remaining + 0.0001 < $difference) {
                throw new RuntimeException('لا يمكن عكس الجرد لأن طبقة FIFO استُهلكت جزئياً');
            }

            $invoiceId = $layer->purchase_invoice_id;
            $layer->delete();

            $invoice = PurchaseInvoice::query()->find($invoiceId);

            if ($invoice && ! $invoice->lines()->exists()) {
                $invoice->delete();
            }

            return;
        }

        $layer->decrement('remaining_qty_primary', $difference);
    }

    protected function restoreDeficitFifo(InventoryCountLine $countLine, float $quantity, float $unitCost): void
    {
        $layer = PurchaseInvoiceLine::query()
            ->where('item_id', $countLine->item_id)
            ->whereHas('purchaseInvoice', fn ($query) => $query->where('warehouse_id', $countLine->warehouse_id))
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($layer) {
            $layer->increment('remaining_qty_primary', $quantity);

            return;
        }

        $this->createSurplusPurchaseLayer($countLine, $quantity, $unitCost);
    }

    protected function createSurplusPurchaseLayer(
        InventoryCountLine $countLine,
        float $quantity,
        float $unitCost,
    ): PurchaseInvoiceLine {
        $item = Item::query()->findOrFail($countLine->item_id);
        $lineTotal = round($quantity * $unitCost, 3);

        $invoice = PurchaseInvoice::query()->create([
            'invoice_date' => now()->toDateString(),
            'supplier_id' => null,
            'payment_method_id' => 1,
            'warehouse_id' => $countLine->warehouse_id,
            'lines_subtotal' => $lineTotal,
            'discount' => 0,
            'amount_paid' => 0,
            'balance' => 0,
            'notes' => 'تسوية جرد — سطر رقم '.(string) $countLine->id,
            'created_by' => Auth::id(),
        ]);

        $line = PurchaseInvoiceLine::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'item_id' => $countLine->item_id,
            'barcode' => $item->barcode,
            'qty_primary' => $quantity,
            'qty_secondary' => 0,
            'unit_cost_primary' => $unitCost,
            'line_cost_total' => $lineTotal,
            'remaining_qty_primary' => $quantity,
            'remaining_qty_secondary' => 0,
            'created_by' => Auth::id(),
        ]);

        $invoice->recalculateTotals();

        return $line;
    }

    protected function consumeFifoLayers(int $itemId, int $warehouseId, float $quantity): void
    {
        $remaining = $quantity;

        $layers = PurchaseInvoiceLine::query()
            ->where('item_id', $itemId)
            ->where('remaining_qty_primary', '>', 0)
            ->whereHas('purchaseInvoice', fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if ($remaining <= 0.0001) {
                break;
            }

            $available = (float) $layer->remaining_qty_primary;
            $take = min($available, $remaining);

            $layer->update([
                'remaining_qty_primary' => $available - $take,
            ]);

            $remaining -= $take;
        }
    }

    protected function recordMovement(InventoryCountLine $line, float $difference): void
    {
        StockMovement::query()->create([
            'warehouse_id' => $line->warehouse_id,
            'item_id' => $line->item_id,
            'movement_type' => 'inventory_count',
            'reference_type' => InventoryCountLine::class,
            'reference_id' => $line->id,
            'qty_primary' => $difference,
            'qty_secondary' => 0,
            'unit_cost' => abs($difference) > 0.0001
                ? round((float) $line->value_amount / $difference, 3)
                : null,
            'notes' => 'جرد مخزن',
            'movement_date' => now(),
            'created_by' => Auth::id(),
        ]);
    }
}
