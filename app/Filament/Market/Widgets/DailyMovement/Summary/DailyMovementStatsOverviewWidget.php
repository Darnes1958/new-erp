<?php

namespace App\Filament\Market\Widgets\DailyMovement\Summary;

use App\Filament\Market\Widgets\DailyMovement\Concerns\InteractsWithDailyMovementFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class DailyMovementStatsOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithDailyMovementFilters;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $stats = $this->dailyMovementService()->statsSummary(
            $this->dateFrom,
            $this->dateTo,
            $this->warehouseId,
        );

        return [
            Stat::make('', number_format($stats['purchases'], 3, '.', ','))
                ->label(new HtmlString('<span class="text-indigo-700">مشتريات</span>'))
                ->color('primary'),
            Stat::make('', number_format($stats['sales'], 3, '.', ','))
                ->label(new HtmlString('<span class="text-indigo-700">مبيعات</span>'))
                ->color('danger'),
            Stat::make('', number_format($stats['collections'], 3, '.', ','))
                ->label(new HtmlString('<span class="text-indigo-700">قبض</span>'))
                ->color('success'),
            Stat::make('', number_format($stats['payments'], 3, '.', ','))
                ->label(new HtmlString('<span class="text-indigo-700">دفع</span>'))
                ->color('danger'),
            Stat::make('', number_format($stats['purchase_returns'], 3, '.', ','))
                ->label(new HtmlString('<span class="text-indigo-700">ترجيع مشتريات</span>'))
                ->color('warning'),
            Stat::make('', number_format($stats['sales_returns'], 3, '.', ','))
                ->label(new HtmlString('<span class="text-indigo-700">ترجيع مبيعات</span>'))
                ->color('warning'),
            Stat::make('', number_format($stats['expenses'], 3, '.', ','))
                ->label(new HtmlString('<span class="text-indigo-700">مصروفات</span>'))
                ->color('gray'),
            Stat::make('', number_format($stats['net_cash_flow'], 3, '.', ','))
                ->label(new HtmlString('<span class="text-indigo-700">صافي التدفق النقدي</span>'))
                ->color($stats['net_cash_flow'] >= 0 ? 'success' : 'danger'),
        ];
    }
}
