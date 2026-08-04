<?php

namespace App\Providers\Filament;

use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Middleware\FilamentAuthenticateSession;
use App\Filament\Market\Pages\InpBuy\InpBuy;
use App\Filament\Market\Pages\InpSell\InpSell;
use App\Filament\Market\Pages\InpSellOffer\InpSellOffer;
use App\Filament\Market\Pages\ListSalesReturns;
use App\Filament\Market\Pages\QuickSell\QuickSell;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Filament\Widgets\PanelSwitcherWidget;
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
            ->breadcrumbs(false)
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
            ])
            ->discoverWidgets(
                in: app_path('Filament/Market/Widgets'),
                for: 'App\\Filament\\Market\\Widgets',
            )
            ->widgets([
                PanelSwitcherWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make(MarketNavigationGroup::PurchaseInvoices),
                NavigationGroup::make(MarketNavigationGroup::SalesInvoices),
                NavigationGroup::make(MarketNavigationGroup::CustomersSuppliers),
                NavigationGroup::make(MarketNavigationGroup::WarehousesItems),
                NavigationGroup::make(MarketNavigationGroup::ReceiptsAndPayments),
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
            ]);
    }
}
