<?php

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\StockMovement;
use App\Models\WarehouseStock;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;

class PurchaseInventoryService
{
    public function applyPurchaseLine(
        int $itemId,
        int $warehouseId,
        float $qtyPrimary,
        float $qtySecondary,
        int $paymentMethodId,
        float $unitCost,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?DateTimeInterface $movementDate = null,
        ?string $notes = null,
    ): void {
        $this->syncBuyPrice($itemId, $paymentMethodId, $unitCost);

        $stock = WarehouseStock::query()->firstOrNew([
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
        ]);

        $stock->quantity_primary = (float) $stock->quantity_primary + $qtyPrimary;
        $stock->quantity_secondary = (float) $stock->quantity_secondary + $qtySecondary;
        $stock->save();

        if ($referenceType !== null && $referenceId !== null) {
            StockMovement::query()->create([
                'warehouse_id' => $warehouseId,
                'item_id' => $itemId,
                'movement_type' => 'purchase',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'qty_primary' => $qtyPrimary,
                'qty_secondary' => $qtySecondary,
                'unit_cost' => $unitCost,
                'notes' => $notes,
                'movement_date' => $movementDate ?? now(),
                'created_by' => Auth::id(),
            ]);
        }
    }

    public function reversePurchaseLine(
        int $itemId,
        int $warehouseId,
        float $qtyPrimary,
        float $qtySecondary,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?DateTimeInterface $movementDate = null,
        ?string $notes = null,
    ): void {
        if ($qtyPrimary == 0.0 && $qtySecondary == 0.0) {
            return;
        }

        $stock = WarehouseStock::query()->firstOrNew([
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
        ]);

        $stock->quantity_primary = (float) $stock->quantity_primary - $qtyPrimary;
        $stock->quantity_secondary = (float) $stock->quantity_secondary - $qtySecondary;
        $stock->save();

        if ($referenceType !== null && $referenceId !== null) {
            StockMovement::query()->create([
                'warehouse_id' => $warehouseId,
                'item_id' => $itemId,
                'movement_type' => 'purchase_reversal',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'qty_primary' => -abs($qtyPrimary),
                'qty_secondary' => -abs($qtySecondary),
                'unit_cost' => null,
                'notes' => $notes,
                'movement_date' => $movementDate ?? now(),
                'created_by' => Auth::id(),
            ]);
        }
    }

    public function syncBuyPrice(int $itemId, int $paymentMethodId, float $unitCost): void
    {
        if ($unitCost <= 0) {
            return;
        }

        ItemPrice::query()->updateOrCreate(
            [
                'item_id' => $itemId,
                'payment_method_id' => $paymentMethodId,
                'price_kind' => 'buy',
            ],
            [
                'price_primary' => $unitCost,
                'price_secondary' => 0,
            ],
        );

        if ($paymentMethodId === 1) {
            Item::query()->whereKey($itemId)->update(['default_buy_price' => $unitCost]);
        }
    }

    public function updateSellPrice(int $itemId, int $paymentMethodId, float $price): void
    {
        ItemPrice::query()->updateOrCreate(
            [
                'item_id' => $itemId,
                'payment_method_id' => $paymentMethodId,
                'price_kind' => 'sell',
            ],
            [
                'price_primary' => $price,
                'price_secondary' => 0,
            ],
        );
    }
}
