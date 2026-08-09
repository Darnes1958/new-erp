<?php

namespace App\Filament\Widgets;

use App\Support\DashboardPresentation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $user = DashboardPresentation::user();
        $company = DashboardPresentation::company();

        return [
            Stat::make('مرحباً', $user?->name ?? '—')
                ->description(DashboardPresentation::panelWelcomeLine())
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
            Stat::make('النسخة مرخصة لـ', $company?->display_name ?? '—')
                ->description(filled($company?->display_name_suffix) ? $company->display_name_suffix : 'نظام ERP')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),
            Stat::make('تاريخ اليوم', now()->translatedFormat('Y-m-d'))
                ->description(now()->translatedFormat('l'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),
        ];
    }
}
