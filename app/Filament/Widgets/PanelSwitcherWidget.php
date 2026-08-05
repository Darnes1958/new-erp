<?php

namespace App\Filament\Widgets;

use App\Support\PanelNavigation;
use Filament\Widgets\Widget;

class PanelSwitcherWidget extends Widget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.panel-switcher';

    protected function getViewData(): array
    {
        return [
            'panels' => collect(PanelNavigation::availablePanels())
                ->map(fn (array $panel): array => [
                    ...$panel,
                    'description' => match ($panel['id']) {
                        'market' => 'فواتير الشراء والبيع، الزبائن، المخازن',
                        'ins' => 'عقود الأقساط، الخصومات، الحافظات',
                        'admin' => 'المستخدمون وإعدادات الشركة',
                        default => '',
                    },
                    'color' => match ($panel['id']) {
                        'market' => 'amber',
                        'ins' => 'emerald',
                        'admin' => 'slate',
                        default => 'gray',
                    },
                ])
                ->all(),
        ];
    }
}
