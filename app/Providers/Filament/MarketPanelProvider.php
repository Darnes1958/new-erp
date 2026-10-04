<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Middleware\FilamentAuthenticateSession;
use App\Filament\Market\Pages\InpBuy\InpBuy;
use App\Filament\Market\Pages\InpSell\InpSell;
use App\Filament\Market\Pages\InpSellOffer\InpSellOffer;
use App\Filament\Market\Pages\InpWarehouseTransfer\InpWarehouseTransfer;
use App\Filament\Market\Pages\ListSalesReturns;
use App\Filament\Market\Pages\QuickSell\QuickSell;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Support\DashboardWidgets;
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
            ->breadcrumbs(false)
            ->profile(EditProfile::class)
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
                InpBuy::class,
                InpSell::class,
                QuickSell::class,
                InpSellOffer::class,
                ListSalesReturns::class,
                InpWarehouseTransfer::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Market/Widgets'),
                for: 'App\\Filament\\Market\\Widgets',
            )
            ->widgets(DashboardWidgets::forPanel())
            ->navigationGroups([
                NavigationGroup::make(MarketNavigationGroup::PurchaseInvoices),
                NavigationGroup::make(MarketNavigationGroup::SalesInvoices),
                NavigationGroup::make(MarketNavigationGroup::CustomersSuppliers),
                NavigationGroup::make(MarketNavigationGroup::WarehousesItems),
                NavigationGroup::make(MarketNavigationGroup::DailyMovement),
                NavigationGroup::make(MarketNavigationGroup::ReceiptsAndPayments),
                NavigationGroup::make(MarketNavigationGroup::BanksAndCashBoxes),
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
                        .market-compact-exports {
                            display: flex;
                            flex-wrap: nowrap;
                            align-items: center;
                            gap: 0.25rem;
                            direction: ltr;
                        }

                        .purchase-invoices-table .fi-ta-record-content-ctn {
                            flex-wrap: wrap;
                            align-items: flex-start !important;
                        }

                        .purchase-invoices-table .fi-ta-record-content-ctn > div:first-child {
                            flex: 1 1 auto;
                            min-width: 0;
                        }

                        .purchase-invoices-table .fi-ta-record-content.fi-collapsible {
                            flex: 1 0 100%;
                            width: 100%;
                            max-width: 100%;
                        }

                        /* Quick-create customer modal — distinct from main page surface */
                        .fi-modal-window.market-create-customer-modal {
                            background-color: #eef8f3 !important;
                            border: 1px solid #8fc9b0;
                            box-shadow: 0 18px 40px rgba(20, 83, 65, 0.18);
                        }

                        .fi-modal-window.market-create-customer-modal .fi-modal-header {
                            background: linear-gradient(180deg, #d9f0e6 0%, #eef8f3 100%);
                            border-bottom: 1px solid #b7ddcd;
                            margin-block-end: 0.75rem;
                            padding-block: 0.9rem;
                        }

                        .fi-modal-window.market-create-customer-modal .fi-modal-heading {
                            color: #14532d;
                            font-weight: 700;
                        }

                        .fi-modal-window.market-create-customer-modal .fi-modal-footer {
                            background-color: #e3f4ec;
                            border-top: 1px solid #b7ddcd;
                        }

                        .dark .fi-modal-window.market-create-customer-modal {
                            background-color: #14352b !important;
                            border-color: #2f6b56;
                        }

                        .dark .fi-modal-window.market-create-customer-modal .fi-modal-header,
                        .dark .fi-modal-window.market-create-customer-modal .fi-modal-footer {
                            background: #1a4033;
                            border-color: #2f6b56;
                        }

                        .dark .fi-modal-window.market-create-customer-modal .fi-modal-heading {
                            color: #bbf7d0;
                        }
                    </style>
                    HTML,
            );
    }
}
