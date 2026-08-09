<?php

namespace App\Filament\Widgets;

use App\Support\DashboardPresentation;
use Filament\Widgets\Widget;

class DashboardMessagesWidget extends Widget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.dashboard-messages';

    public static function canView(): bool
    {
        $settings = DashboardPresentation::settings();

        return filled($settings?->alert_message) || filled($settings?->user_message);
    }

    protected function getViewData(): array
    {
        $settings = DashboardPresentation::settings();

        return [
            'alertMessage' => $settings?->alert_message,
            'userMessage' => $settings?->user_message,
        ];
    }
}
