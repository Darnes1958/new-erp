<?php

namespace App\Filament\Market\Widgets;

use App\Services\Market\ProfitReportService;
use Filament\Widgets\ChartWidget;

class ProfitChartWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected ?string $heading = 'صافي الأرباح حسب الشهر';

    protected ?string $maxHeight = '360px';

    public ?int $year = null;

    public ?int $warehouseId = null;

    protected function getData(): array
    {
        if (blank($this->year)) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $rows = app(ProfitReportService::class)
            ->monthlySummary($this->year, $this->warehouseId);

        return [
            'datasets' => [
                [
                    'label' => 'صافي الأرباح حسب الشهر',
                    'data' => $rows
                        ->pluck('safi')
                        ->map(fn (float $value): float => round($value))
                        ->all(),
                    'backgroundColor' => $this->barColors(),
                ],
            ],
            'labels' => $rows->pluck('month_name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<int, string>
     */
    protected function barColors(): array
    {
        return [
            '#483D8B',
            '#FFB6C1',
            '#7FFF00',
            '#0000FF',
            '#DEB887',
            '#006400',
            '#8B0000',
            '#FF8C00',
            '#483D8B',
            '#8B008B',
            '#2F4F4F',
            '#00CED1',
        ];
    }
}
