<?php

namespace App\Providers\Filament;

use App\Support\DashboardWidgets;
use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Middleware\FilamentAuthenticateSession;
use App\Support\FilamentSidebarStyle;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class FinancePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('finance')
            ->path('finance')
            ->brandName('المالية')
            ->sidebarFullyCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->breadcrumbs(false)
            ->colors([
                'primary' => Color::Violet,
            ])
            ->discoverResources(
                in: app_path('Filament/Finance/Resources'),
                for: 'App\\Filament\\Finance\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Finance/Pages'),
                for: 'App\\Filament\\Finance\\Pages',
            )
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Finance/Widgets'),
                for: 'App\\Filament\\Finance\\Widgets',
            )
            ->widgets(DashboardWidgets::forPanel())
            ->navigationGroups([
                NavigationGroup::make('مصروفات'),
                NavigationGroup::make('مرتبات'),
                NavigationGroup::make('إيجارات'),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                FilamentAuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                FilamentAuthenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => FilamentSidebarStyle::headEndHtml().<<<'HTML'
                    <style>
                        .finance-compact-exports {
                            display: flex;
                            flex-wrap: nowrap;
                            align-items: center;
                            gap: 0.25rem;
                            direction: ltr;
                        }
                    </style>
                    HTML,
            );
    }
}
