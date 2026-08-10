<?php

namespace App\Services\Market;

use App\Models\WarehouseStockReportEntry;
use Illuminate\Database\Eloquent\Builder;

class WarehouseStockReportService
{
    public function reportQuery(
        ?int $warehouseId = null,
        bool $includeZero = false,
    ): Builder {
        return WarehouseStockReportEntry::query()
            ->when(
                $warehouseId,
                fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId),
            )
            ->when(
                ! $includeZero,
                fn (Builder $query): Builder => $query->where('warehouse_qty_primary', '!=', 0),
            );
    }

    public function applyDefaultOrdering(Builder $query): Builder
    {
        return $query
            ->orderBy('item_name')
            ->orderBy('warehouse_name');
    }

    /**
     * @return array{warehouse_cost_total: float}
     */
    public function summary(?int $warehouseId = null, bool $includeZero = false): array
    {
        $row = $this->reportQuery($warehouseId, $includeZero)
            ->selectRaw('COALESCE(SUM(warehouse_cost_total), 0) AS warehouse_cost_total')
            ->first();

        return [
            'warehouse_cost_total' => round((float) ($row->warehouse_cost_total ?? 0), 3),
        ];
    }
}
