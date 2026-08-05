<?php

namespace App\Providers\Filament;

use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Middleware\FilamentAuthenticateSession;
use App\Filament\Widgets\PanelSwitcherWidget;
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
            ->breadcrumbs(false)
            ->colors([
                'primary' => Color::Amber,
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
                fn (): string => <<<'HTML'
                    <style>
                        .fi-ins-export-header-page .fi-header-actions-ctn > .fi-ac {
                            direction: ltr !important;
                            justify-content: flex-start !important;
                            gap: 1.5rem !important;
                            width: auto !important;
                            flex: 0 0 auto !important;
                        }

                        .ins-report-toolbar {
                            display: flex;
                            flex-wrap: wrap;
                            align-items: flex-start;
                            justify-content: space-between;
                            gap: 1rem;
                        }

                        .ins-report-toolbar__filters {
                            flex: 1 1 auto;
                            min-width: 280px;
                            max-width: 520px;
                        }

                        .ins-report-toolbar__filters--branch {
                            max-width: 360px;
                        }

                        .ins-report-toolbar__filters--wide {
                            max-width: 720px;
                        }

                        .ins-report-toolbar__exports {
                            display: flex;
                            flex-wrap: wrap;
                            gap: 0.75rem;
                            direction: ltr;
                        }
                    </style>
                HTML,
            );
    }
}
