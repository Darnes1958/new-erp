<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\PanelSwitcherWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class MarketPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('market')
            ->path('market')
            ->login()
            ->brandName('المبيعات')
            ->sidebarFullyCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(
                in: app_path('Filament/Market/Resources'),
                for: 'App\\Filament\\Market\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Market/Pages'),
                for: 'App\\Filament\\Market\\Pages',
            )
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Market/Widgets'),
                for: 'App\\Filament\\Market\\Widgets',
            )
            ->widgets([
                PanelSwitcherWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('فواتير شراء'),
                NavigationGroup::make('فواتير مبيعات'),
                NavigationGroup::make('زبائن وموردين'),
                NavigationGroup::make('مخازن وأصناف'),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
