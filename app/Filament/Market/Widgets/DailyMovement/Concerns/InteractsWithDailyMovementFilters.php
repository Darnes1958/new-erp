<?php

namespace App\Filament\Market\Widgets\DailyMovement\Concerns;

use App\Services\Market\DailyMovementReportService;
use Livewire\Attributes\On;

trait InteractsWithDailyMovementFilters
{
    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?int $warehouseId = null;

    public function mount(?string $dateFrom = null, ?string $dateTo = null, ?int $warehouseId = null): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->warehouseId = $warehouseId;
    }

    #[On('daily-movement-filters-updated')]
    public function refreshDailyMovementFilters(?string $dateFrom, ?string $dateTo, ?int $warehouseId): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->warehouseId = $warehouseId;
    }

    protected function dailyMovementService(): DailyMovementReportService
    {
        return app(DailyMovementReportService::class);
    }
}
