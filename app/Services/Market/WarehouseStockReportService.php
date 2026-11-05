<?php

namespace App\Services\Market;

use App\Models\WarehouseStockReportEntry;
use Illuminate\Database\Eloquent\Builder;

class WarehouseStockReportService
{
    public function reportQuery(
        ?int $warehouseId = null,
        bool $includeZero = false,
        ?int $itemTypeId = null,
    ): Builder {
        return WarehouseStockReportEntry::query()
            ->leftJoin('items as stock_items', 'stock_items.id', '=', 'warehouse_stock_report_entries.item_id')
            ->leftJoin('item_types as stock_item_types', 'stock_item_types.id', '=', 'stock_items.item_type_id')
            ->select('warehouse_stock_report_entries.*')
            ->addSelect('stock_items.item_type_id as item_type_id')
            ->addSelect('stock_item_types.name as item_type_name')
            ->when(
                $warehouseId,
                fn (Builder $query): Builder => $query->where('warehouse_stock_report_entries.warehouse_id', $warehouseId),
            )
            ->when(
                $itemTypeId,
                fn (Builder $query): Builder => $query->where('stock_items.item_type_id', $itemTypeId),
            )
            ->when(
                ! $includeZero,
                fn (Builder $query): Builder => $query->where('warehouse_stock_report_entries.warehouse_qty_primary', '!=', 0),
            );
    }

    public function applyDefaultOrdering(Builder $query): Builder
    {
        return $query
            ->orderBy('stock_item_types.name')
            ->orderBy('warehouse_stock_report_entries.item_name')
            ->orderBy('warehouse_stock_report_entries.warehouse_name');
    }

    /**
     * @return array{warehouse_cost_total: float}
     */
    public function summary(?int $warehouseId = null, bool $includeZero = false, ?int $itemTypeId = null): array
    {
        $total = $this->reportQuery($warehouseId, $includeZero, $itemTypeId)
            ->reorder()
            ->sum('warehouse_stock_report_entries.warehouse_cost_total');

        return [
            'warehouse_cost_total' => round((float) $total, 3),
        ];
    }
}
