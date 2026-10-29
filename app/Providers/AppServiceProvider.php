<?php

namespace App\Providers;

use App\Http\Responses\MarketPanelLoginResponse;
use App\Models\InstallmentCancelledContract;
use App\Models\InstallmentCancelledDeduction;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\InstallmentDeduction;
use App\Models\WrongDeduction;
use App\Models\InstallmentSurplus;
use App\Models\InstallmentSuspended;
use App\Observers\InstallmentContractableMetricsObserver;
use App\Observers\InstallmentDeductionObserver;
use App\Support\Filament\TableSummaries;
use App\Support\PdfChrome;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Lang;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoginResponseContract::class, MarketPanelLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Aliases for converted installment morph types only; other models (e.g. User) keep full class names.
        Relation::morphMap([
            'installment_contract' => InstallmentContract::class,
            'installment_contract_archive' => InstallmentContractArchive::class,
            'installment_contract_cancelled' => InstallmentCancelledContract::class,
            'installment_cancelled_deduction' => InstallmentCancelledDeduction::class,
            'installment_surplus' => InstallmentSurplus::class,
            'wrong_deduction' => WrongDeduction::class,
            // Legacy ERP morph types stored during data conversion.
            'App\Models\Main' => InstallmentContract::class,
            'App\Models\Main_arc' => InstallmentContractArchive::class,
            'App\Models\Overkst' => InstallmentSurplus::class,
            'App\Models\Wrongkst' => WrongDeduction::class,
        ]);

        Pdf::default()
            ->footerView('pdf.footer')
            ->margins(10, 10, 10, 10)
            ->withBrowsershot(function (Browsershot $shot): void {
                if (config('laravel-pdf.browsershot.no_sandbox')) {
                    $shot->noSandbox();
                }

                $chromePath = PdfChrome::resolve();

                if ($chromePath !== null) {
                    $shot->setChromePath($chromePath);
                }

                $nodeBinary = config('laravel-pdf.browsershot.node_binary');

                if (is_string($nodeBinary) && $nodeBinary !== '') {
                    $shot->setNodeBinary($nodeBinary);
                }

                $nodeModulesPath = config('laravel-pdf.browsershot.node_modules_path');

                if (is_string($nodeModulesPath) && $nodeModulesPath !== '') {
                    $shot->setNodeModulePath($nodeModulesPath);
                }

                $tempPath = config('laravel-pdf.browsershot.temp_path');

                if (is_string($tempPath) && $tempPath !== '') {
                    $shot->setTemporaryDirectory($tempPath);
                }
            });

        $numberLocale = config('erp.number_locale', 'en_US');

        Number::useLocale($numberLocale);

        $filamentTableLines = [
            'table.summary.heading' => '',
            'table.summary.subheadings.all' => '',
            'table.summary.subheadings.page' => '',
            'table.summary.subheadings.group' => '',
            'table.summary.summarizers.sum.label' => '',
            'table.summary.summarizers.average.label' => '',
            'table.summary.summarizers.count.label' => '',
            'table.filters.heading' => 'تصفية',
            'table.filters.indicator' => 'التصفيات النشطة',
            'table.filters.actions.apply.label' => 'تطبيق التصفية',
            'table.filters.actions.reset.label' => 'إعادة تعيين',
            'table.filters.actions.remove.label' => 'إزالة التصفية',
            'table.filters.actions.remove_all.label' => 'إزالة كل التصفيات',
            'table.filters.actions.remove_all.tooltip' => 'إزالة كل التصفيات',
            'table.filters.select.placeholder' => 'الكل',
            'table.filters.multi_select.placeholder' => 'الكل',
            'table.fields.search.label' => 'بحث',
            'table.fields.search.placeholder' => 'بحث',
            'table.fields.search.indicator' => 'بحث',
            'table.actions.filter.label' => 'تصفية',
            'table.selection_indicator.selected_count' => '{1} تم تحديد سجل واحد|{2} تم تحديد سجلين|[3,10] تم تحديد :count سجلات|[11,*] تم تحديد :count سجل',
            'table.selection_indicator.actions.select_all.label' => 'تحديد الكل (:count)',
            'table.selection_indicator.actions.deselect_all.label' => 'إلغاء تحديد الكل',
        ];

        $translator = app('translator');

        foreach (array_unique(['ar', 'en', (string) config('app.locale')]) as $locale) {
            $translator->load('filament-tables', 'table', $locale);
            Lang::addLines($filamentTableLines, $locale, 'filament-tables');
        }

        Table::configureUsing(fn (Table $table) => TableSummaries::applyDefaults($table)
            ->defaultNumberLocale($numberLocale)
            ->pluralModelLabel('الصفحات')
            ->emptyStateHeading('لا توجد بيانات')
            ->defaultKeySort(false)
        );

        Schema::configureUsing(fn (Schema $schema) => $schema
            ->defaultNumberLocale($numberLocale)
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
            fn (): string => Blade::render(
                <<<'BLADE'
                    <div class="fi-topbar-panel-tools">
                        @livewire('panel-switcher')
                        @livewire('company-switcher')
                        @include('components.database-backup-button')
                    </div>
                BLADE
            ),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => <<<'HTML'
                <style>
                    .fi-topbar-panel-tools {
                        display: flex;
                        align-items: center;
                        gap: 0.75rem;
                        margin-inline-end: 0.5rem;
                    }

                    .fi-panel-switcher .fi-fo-toggle-buttons {
                        margin: 0;
                    }
                </style>
                HTML,
        );

        InstallmentDeduction::observe(InstallmentDeductionObserver::class);
        InstallmentSurplus::observe(InstallmentContractableMetricsObserver::class);
        InstallmentSuspended::observe(InstallmentContractableMetricsObserver::class);
    }
}
