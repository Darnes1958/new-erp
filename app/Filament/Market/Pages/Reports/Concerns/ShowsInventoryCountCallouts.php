<?php

namespace App\Filament\Market\Pages\Reports\Concerns;

use App\Services\Market\ProfitReportService;
use Filament\Schemas\Components\Callout;

trait ShowsInventoryCountCallouts
{
    /**
     * @return array<int, Callout>
     */
    protected function inventoryCountCallouts(ProfitReportService $service): array
    {
        $year = $this->year ?? (int) now()->format('Y');
        $warehouseId = method_exists($this, 'profitReportWarehouseId')
            ? $this->profitReportWarehouseId()
            : null;

        if (! $service->hasInventoryCountForYear($year, $warehouseId)) {
            return [];
        }

        return [
            Callout::make('فائض جرد')
                ->success()
                ->description(number_format(
                    $service->inventorySurplusTotal($year, $warehouseId),
                    3,
                    '.',
                    ',',
                )),
            Callout::make('عجز جرد')
                ->danger()
                ->description(number_format(
                    $service->inventoryDeficitTotal($year, $warehouseId),
                    3,
                    '.',
                    ',',
                )),
        ];
    }
}
