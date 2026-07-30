<?php

namespace App\Filament\Widgets;

use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class PanelSwitcherWidget extends Widget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.panel-switcher';

    protected function getViewData(): array
    {
        $current = Filament::getCurrentPanel()?->getId();

        return [
            'panels' => [
                [
                    'id' => 'market',
                    'label' => 'المبيعات',
                    'description' => 'فواتير الشراء والبيع، الزبائن، المخازن',
                    'url' => Filament::getPanel('market')->getUrl(),
                    'active' => $current === 'market',
                    'color' => 'amber',
                ],
                [
                    'id' => 'ins',
                    'label' => 'التقسيط',
                    'description' => 'عقود الأقساط، الخصومات، الحافظات',
                    'url' => Filament::getPanel('ins')->getUrl(),
                    'active' => $current === 'ins',
                    'color' => 'emerald',
                ],
                [
                    'id' => 'admin',
                    'label' => 'الإدارة',
                    'description' => 'المستخدمون وإعدادات الشركة',
                    'url' => Filament::getPanel('admin')->getUrl(),
                    'active' => $current === 'admin',
                    'color' => 'slate',
                ],
            ],
        ];
    }
}
