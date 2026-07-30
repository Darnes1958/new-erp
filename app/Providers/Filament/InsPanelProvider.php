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

class InsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('ins')
            ->path('ins')
            ->brandName('التقسيط')
            ->sidebarFullyCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverResources(
                in: app_path('Filament/Ins/Resources'),
                for: 'App\\Filament\\Ins\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Ins/Pages'),
                for: 'App\\Filament\\Ins\\Pages',
            )
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Ins/Widgets'),
                for: 'App\\Filament\\Ins\\Widgets',
            )
            ->widgets([
                PanelSwitcherWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('عقود التقسيط'),
                NavigationGroup::make('خصومات ومدفوعات'),
                NavigationGroup::make('تقارير'),
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
