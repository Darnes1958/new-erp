<?php

namespace App\Support;

use App\Filament\Widgets\DashboardMessagesWidget;
use App\Filament\Widgets\DashboardStatsWidget;
use App\Filament\Widgets\PanelSwitcherWidget;
use App\Filament\Widgets\WelcomeDashboardWidget;

class DashboardWidgets
{
    /**
     * @return array<class-string>
     */
    public static function forPanel(): array
    {
        $widgets = [
            WelcomeDashboardWidget::class,
            DashboardStatsWidget::class,
            DashboardMessagesWidget::class,
        ];

        if (PanelNavigation::hasMultiplePanels()) {
            $widgets[] = PanelSwitcherWidget::class;
        }

        return $widgets;
    }
}
