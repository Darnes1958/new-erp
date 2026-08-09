<?php

namespace App\Providers\Filament;

use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Middleware\FilamentAuthenticateSession;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Market\Pages\Reports\ProfitReportPage;
use App\Filament\Market\Pages\Reports\WarehouseProfitReportPage;
use App\Filament\Market\Resources\InventoryCountLines\InventoryCountLineResource;
use App\Filament\Market\Resources\InventoryCountSessions\InventoryCountSessionResource;
use App\Filament\Market\Widgets\ProfitChartWidget;
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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->brandName('الإدارة')
            ->sidebarFullyCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->breadcrumbs(false)
            ->colors([
                'primary' => Color::Slate,
            ])
            ->discoverResources(
                in: app_path('Filament/Admin/Resources'),
                for: 'App\\Filament\\Admin\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Admin/Pages'),
                for: 'App\\Filament\\Admin\\Pages',
            )
            ->pages([
                Dashboard::class,
                ProfitReportPage::class,
                WarehouseProfitReportPage::class,
            ])
            ->resources([
                InventoryCountSessionResource::class,
                InventoryCountLineResource::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Admin/Widgets'),
                for: 'App\\Filament\\Admin\\Widgets',
            )
            ->widgets(DashboardWidgets::forPanel())
            ->livewireComponents([
                ProfitChartWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make(AdminNavigationGroup::Management),
                NavigationGroup::make('المستخدمون'),
                NavigationGroup::make('مراقبة النظام'),
                NavigationGroup::make('استيراد Excel'),
                NavigationGroup::make('إعدادات النظام'),
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
            );
    }
}
