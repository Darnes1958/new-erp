<?php

namespace App\Support;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class PanelNavigation
{
    /**
     * @return array<int, array{id: string, label: string, color: string, icon: Heroicon, url: string, active: bool}>
     */
    public static function availablePanels(): array
    {
        $user = Auth::user();
        $currentId = Filament::getCurrentPanel()?->getId();

        $definitions = [
            [
                'id' => 'market',
                'label' => 'مبيعات',
                'color' => 'info',
                'icon' => Heroicon::OutlinedShoppingCart,
            ],
            [
                'id' => 'ins',
                'label' => 'تقسيط',
                'color' => 'success',
                'icon' => Heroicon::OutlinedBanknotes,
            ],
            [
                'id' => 'finance',
                'label' => 'مالية',
                'color' => 'violet',
                'icon' => Heroicon::OutlinedCurrencyDollar,
            ],
            [
                'id' => 'admin',
                'label' => 'إدارة',
                'color' => 'warning',
                'icon' => Heroicon::OutlinedCog6Tooth,
            ],
        ];

        $panels = [];

        foreach ($definitions as $definition) {
            $panel = Filament::getPanel($definition['id']);

            if (! $user?->canAccessPanel($panel)) {
                continue;
            }

            $panels[] = [
                ...$definition,
                'url' => $panel->getUrl(),
                'active' => $currentId === $definition['id'],
            ];
        }

        return $panels;
    }

    public static function hasMultiplePanels(): bool
    {
        return count(static::availablePanels()) > 1;
    }

    /**
     * @return array<string, string>
     */
    public static function toggleOptions(): array
    {
        return collect(static::availablePanels())
            ->pluck('label', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function toggleColors(): array
    {
        return collect(static::availablePanels())
            ->pluck('color', 'id')
            ->all();
    }

    /**
     * @return array<string, Heroicon|string>
     */
    public static function toggleIcons(): array
    {
        return collect(static::availablePanels())
            ->pluck('icon', 'id')
            ->all();
    }

    public static function panelForId(string $panelId): ?Panel
    {
        foreach (Filament::getPanels() as $panel) {
            if ($panel->getId() === $panelId) {
                return $panel;
            }
        }

        return null;
    }
}
