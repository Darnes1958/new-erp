<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Middleware\FilamentAuthenticateSession;
use App\Support\DashboardWidgets;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use App\Support\FilamentSidebarStyle;
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
            ->profile(EditProfile::class)
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
            ->widgets(DashboardWidgets::forPanel())
            ->navigationGroups([
                NavigationGroup::make('أقساط'),
                NavigationGroup::make('خصومات ومدفوعات'),
                NavigationGroup::make('مصارف'),
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
                fn (): string => FilamentSidebarStyle::headEndHtml(),
            )
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
                            flex-wrap: nowrap;
                            align-items: center;
                            gap: 0.25rem;
                            direction: ltr;
                        }

                        .ins-compact-exports {
                            display: flex;
                            flex-wrap: nowrap;
                            align-items: center;
                            gap: 0.25rem;
                            direction: ltr;
                        }

                        .ins-contract-adjustments {
                            margin-top: 1.35rem;
                            padding: 0.65rem 0.75rem;
                            border-top: 1px dashed rgb(209 213 219);
                            border-radius: 0.75rem;
                            width: 100%;
                            background: rgb(249 250 251 / 0.65);
                        }

                        .ins-contract-adjustments-block {
                            display: flex;
                            flex-direction: column;
                            gap: 0.45rem;
                            width: 100%;
                        }

                        .ins-contract-adjustment-row {
                            display: flex;
                            align-items: center;
                            flex-wrap: wrap;
                            gap: 0.65rem 1.5rem;
                            width: 100%;
                            padding: 0.55rem 0.7rem;
                            border-radius: 0.55rem;
                        }

                        .ins-contract-adjustment-row--surplus {
                            background: rgb(255 251 235 / 0.9);
                        }

                        .ins-contract-adjustment-row--return {
                            background: rgb(239 246 255 / 0.9);
                        }

                        .ins-contract-adjustments .fi-in-entry {
                            width: 100%;
                        }

                        .ins-contract-adjustments .fi-in-entry-content-col,
                        .ins-contract-adjustments .fi-in-entry-content-ctn,
                        .ins-contract-adjustments .fi-in-entry-content {
                            width: 100%;
                        }

                        .ins-contract-adjustment-item {
                            display: inline-flex;
                            align-items: center;
                            gap: 0.35rem;
                            flex: 0 0 auto;
                            border: 0;
                            background: transparent;
                            padding: 0;
                            cursor: pointer;
                            font: inherit;
                            white-space: nowrap;
                        }

                        .ins-contract-adjustment-item__label {
                            font-weight: 600;
                        }

                        .ins-contract-adjustment-item__value {
                            font-weight: 700;
                            font-variant-numeric: tabular-nums;
                            text-decoration: underline;
                            text-underline-offset: 3px;
                        }

                        .ins-contract-adjustment-item--surplus,
                        .ins-contract-adjustment-item--surplus .ins-contract-adjustment-item__value {
                            color: rgb(180 83 9);
                        }

                        .ins-contract-adjustment-item--return,
                        .ins-contract-adjustment-item--return .ins-contract-adjustment-item__value {
                            color: rgb(29 78 216);
                        }

                        .ins-contract-adjustment-item:hover {
                            opacity: 0.82;
                        }

                        .dark .ins-contract-adjustments {
                            border-top-color: rgb(75 85 99);
                            background: rgb(17 24 39 / 0.35);
                        }

                        .dark .ins-contract-adjustment-row--surplus {
                            background: rgb(69 26 3 / 0.32);
                        }

                        .dark .ins-contract-adjustment-row--return {
                            background: rgb(23 37 84 / 0.32);
                        }

                        .dark .ins-contract-adjustment-item--surplus,
                        .dark .ins-contract-adjustment-item--surplus .ins-contract-adjustment-item__value {
                            color: rgb(251 191 36);
                        }

                        .dark .ins-contract-adjustment-item--return,
                        .dark .ins-contract-adjustment-item--return .ins-contract-adjustment-item__value {
                            color: rgb(147 197 253);
                        }

                        .ins-contract-customer-hint {
                            margin-top: 0.85rem;
                            padding: 0.7rem 0.85rem;
                            border: 1px dashed rgb(209 213 219);
                            border-radius: 0.65rem;
                            background: rgb(249 250 251);
                            color: rgb(75 85 99);
                            font-size: 0.925rem;
                            line-height: 1.6;
                        }

                        .ins-contract-customer-hint + .ins-contract-customer-hint {
                            margin-top: 0.45rem;
                        }

                        .ins-contract-customer-hint--active strong {
                            color: rgb(4 120 87);
                            font-weight: 700;
                        }

                        .ins-contract-customer-hint--archive strong {
                            color: rgb(29 78 216);
                            font-weight: 700;
                        }

                        .ins-contract-customer-hint__link {
                            display: inline;
                            margin: 0;
                            padding: 0;
                            border: 0;
                            background: transparent;
                            cursor: pointer;
                            font: inherit;
                            text-decoration: underline;
                            text-underline-offset: 0.15em;
                        }

                        .ins-contract-customer-hint__link:hover strong,
                        .ins-contract-customer-hint__link:focus-visible strong {
                            color: rgb(30 64 175);
                        }

                        .ins-contract-customer-hint--active .ins-contract-customer-hint__link:hover strong,
                        .ins-contract-customer-hint--active .ins-contract-customer-hint__link:focus-visible strong {
                            color: rgb(4 120 87);
                        }

                        .dark .ins-contract-customer-hint {
                            border-color: rgb(75 85 99);
                            background: rgb(31 41 55);
                            color: rgb(209 213 219);
                        }

                        .dark .ins-contract-customer-hint--active strong {
                            color: rgb(110 231 183);
                        }

                        .dark .ins-contract-customer-hint--archive strong {
                            color: rgb(147 197 253);
                        }

                        .dark .ins-contract-customer-hint__link:hover strong,
                        .dark .ins-contract-customer-hint__link:focus-visible strong {
                            color: rgb(191 219 254);
                        }

                        .dark .ins-contract-customer-hint--active .ins-contract-customer-hint__link:hover strong,
                        .dark .ins-contract-customer-hint--active .ins-contract-customer-hint__link:focus-visible strong {
                            color: rgb(110 231 183);
                        }
                    </style>
                HTML,
            );
    }
}
