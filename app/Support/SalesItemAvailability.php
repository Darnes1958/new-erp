<?php

namespace App\Support;

use App\Models\WarehouseStock;
use Illuminate\Database\Eloquent\Builder;

final class SalesItemAvailability
{
    public static function applyWarehouseStockFilter(
        Builder $query,
        ?int $warehouseId,
        ?int $alwaysIncludeItemId = null,
    ): Builder {
        if (! $warehouseId) {
            if ($alwaysIncludeItemId) {
                return $query->whereKey($alwaysIncludeItemId);
            }

            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $inner) use ($warehouseId, $alwaysIncludeItemId): void {
            $inner->whereIn('id', WarehouseStock::query()
                ->where('warehouse_id', $warehouseId)
                ->where('quantity_primary', '>', 0)
                ->select('item_id'));

            if ($alwaysIncludeItemId) {
                $inner->orWhere($inner->getModel()->getQualifiedKeyName(), $alwaysIncludeItemId);
            }
        });
    }
}
