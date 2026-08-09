<?php

namespace App\Filament\Widgets;

use App\Support\DashboardPresentation;
use Filament\Widgets\Widget;

class WelcomeDashboardWidget extends Widget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.welcome-dashboard';

    protected function getViewData(): array
    {
        $user = DashboardPresentation::user();
        $company = DashboardPresentation::company();

        return [
            'userName' => $user?->name ?? '',
            'userEmail' => $user?->email ?? '',
            'userAvatarUrl' => DashboardPresentation::userAvatarUrl($user),
            'companyName' => $company?->display_name,
            'companySuffix' => $company?->display_name_suffix,
            'companyLogoUrl' => DashboardPresentation::companyLogoUrl($company),
            'panelWelcomeLine' => DashboardPresentation::panelWelcomeLine(),
            'panelLabel' => DashboardPresentation::panelLabel(),
            'roleBadges' => DashboardPresentation::roleBadges($user),
            'todayLabel' => now()->translatedFormat('l j F Y'),
        ];
    }
}
