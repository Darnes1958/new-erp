<?php

namespace App\Services\Market;

use App\Models\ItemMovementEntry;
use Illuminate\Database\Eloquent\Builder;

class ItemMovementReportService
{
    public function movementQuery(
        ?int $itemId,
        ?string $dateFrom,
        ?int $warehouseId = null,
    ): Builder {
        if (! filled($itemId)) {
            return ItemMovementEntry::query()->whereRaw('0 = 1');
        }

        return ItemMovementEntry::query()
            ->where('item_id', $itemId)
            ->when(
                filled($dateFrom),
                fn (Builder $query): Builder => $query->whereDate('order_date', '>=', $dateFrom),
            )
            ->when(
                $warehouseId,
                fn (Builder $query): Builder => $query->where('place_id', $warehouseId),
            );
    }

    public function applyDefaultOrdering(Builder $query): Builder
    {
        return $query
            ->orderBy('created_at')
            ->orderBy('source_order')
            ->orderBy('id');
    }

    public function movementTypeColor(?string $type): ?string
    {
        return match ($type) {
            'مشتريات' => 'info',
            'مبيعات' => 'success',
            'جرد' => 'danger',
            'ترجيع مبيعات' => 'warning',
            'نقل اصناف' => 'gray',
            default => null,
        };
    }
}
