<?php

namespace App\Support;

use App\Models\WarehouseStock;
use Illuminate\Database\Eloquent\Builder;

final class SalesItemAvailability
{
    public static function applyWarehouseStockFilter(Builder $query, ?int $warehouseId): Builder
    {
        if (! $warehouseId) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('id', WarehouseStock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('quantity_primary', '>', 0)
            ->select('item_id'));
    }
}
