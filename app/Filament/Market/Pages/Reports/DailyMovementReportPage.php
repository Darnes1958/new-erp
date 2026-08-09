<?php

namespace App\Filament\Market\Pages\Reports;

use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Filament\Market\Widgets\DailyMovement\Detail\CustomerReceiptsTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Detail\ExpensesTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Detail\PurchaseReturnsTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Detail\PurchasesTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Detail\RentsTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Detail\SalariesTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Detail\SalesReturnsTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Detail\SalesTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Detail\SupplierPaymentsTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Summary\CashBoxesSummaryTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Summary\CustomerReceiptsSummaryTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Summary\ExpensesSummaryTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Summary\PurchaseReturnsSummaryTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Summary\PurchasesSummaryTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Summary\RentsSummaryTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Summary\SalariesSummaryTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Summary\SalesReturnsSummaryTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Summary\SalesSummaryTableWidget;
use App\Filament\Market\Widgets\DailyMovement\Summary\DailyMovementStatsOverviewWidget;
use App\Filament\Market\Widgets\DailyMovement\Summary\SupplierPaymentsSummaryTableWidget;
use App\Models\Warehouse;
use App\Services\Excel\MarketExcelService;
use App\Services\Market\DailyMovementReportService;
use App\Services\Pdf\DailyMovementPdfService;
use App\Support\PdfDownload;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class DailyMovementReportPage extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationLabel = 'الحركة اليومية';

    protected static ?string $title = 'الحركة اليومية';

    protected static ?string $slug = 'daily-movement-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::DailyMovement;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.market.pages.reports.daily-movement-report';

    protected ?string $heading = '';

    #[Url(as: 'tab')]
    public string $activeTab = 'detail';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?int $warehouseId = null;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('تقارير');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        if (! in_array($this->activeTab, ['detail', 'summary'], true)) {
            $this->activeTab = 'detail';
        }

        $today = now()->toDateString();
        $this->dateFrom ??= now()->startOfYear()->toDateString();
        $this->dateTo ??= $today;

        $this->refreshFiltersForm();
    }

    protected function getForms(): array
    {
        return [
            'filtersForm',
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->schema([
                        DatePicker::make('dateFrom')
                            ->columnSpan(2)
                            ->label('من تاريخ')
                            ->live(),
                        DatePicker::make('dateTo')
                            ->columnSpan(2)
                            ->label('إلى تاريخ')
                            ->live(),
                        Select::make('warehouseId')
                            ->columnSpan(2)
                            ->label('المخزن')
                            ->placeholder('الكل')
                            ->options(fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->live(),
                        Actions::make([
                            InstallmentListPrintActions::compactPrint('print', fn () => $this->downloadPdf()),
                            InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadExcel()),
                        ])
                            ->columnSpan(2)
                            ->extraAttributes(['class' => 'market-compact-exports']),
                    ])
                    ->columns(8),
            ]);
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['detail', 'summary'], true)) {
            return;
        }

        $this->activeTab = $tab;
        unset($this->cachedFooterWidgetsSchemaComponents);
    }

    public function updatedDateFrom(): void
    {
        $this->broadcastFilterUpdate();
    }

    public function updatedDateTo(): void
    {
        $this->broadcastFilterUpdate();
    }

    public function updatedWarehouseId(): void
    {
        $this->broadcastFilterUpdate();
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    protected function getFooterWidgets(): array
    {
        $filters = [
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'warehouseId' => $this->warehouseId,
        ];

        if ($this->activeTab === 'summary') {
            return [
                DailyMovementStatsOverviewWidget::make($filters),
                PurchasesSummaryTableWidget::make($filters),
                SalesSummaryTableWidget::make($filters),
                SupplierPaymentsSummaryTableWidget::make($filters),
                CustomerReceiptsSummaryTableWidget::make($filters),
                ExpensesSummaryTableWidget::make($filters),
                SalariesSummaryTableWidget::make($filters),
                RentsSummaryTableWidget::make($filters),
                SalesReturnsSummaryTableWidget::make($filters),
                PurchaseReturnsSummaryTableWidget::make($filters),
                CashBoxesSummaryTableWidget::make($filters),
            ];
        }

        return [
            PurchasesTableWidget::make($filters),
            SalesTableWidget::make($filters),
            SupplierPaymentsTableWidget::make($filters),
            CustomerReceiptsTableWidget::make($filters),
            SalesReturnsTableWidget::make($filters),
            PurchaseReturnsTableWidget::make($filters),
            ExpensesTableWidget::make($filters),
            SalariesTableWidget::make($filters),
            RentsTableWidget::make($filters),
        ];
    }

    public function getFooterWidgetsColumns(): int | array
    {
        return 1;
    }

    protected function broadcastFilterUpdate(): void
    {
        unset($this->cachedFooterWidgetsSchemaComponents);

        $this->dispatch(
            'daily-movement-filters-updated',
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            warehouseId: $this->warehouseId,
        );

        $this->refreshFiltersForm();
    }

    protected function refreshFiltersForm(): void
    {
        $this->filtersForm->fill([
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'warehouseId' => $this->warehouseId,
        ]);
    }

    protected function downloadPdf(): mixed
    {
        if (! filled($this->dateFrom) && ! filled($this->dateTo)) {
            Notification::make()->title('حدد الفترة الزمنية')->warning()->send();

            return null;
        }

        $service = app(DailyMovementReportService::class);
        $warehouseName = filled($this->warehouseId)
            ? Warehouse::query()->whereKey($this->warehouseId)->value('name')
            : null;

        $pdfService = app(DailyMovementPdfService::class);

        if ($this->activeTab === 'summary') {
            return PdfDownload::streamed(
                $pdfService->summary(
                    $service,
                    $this->dateFrom,
                    $this->dateTo,
                    $this->warehouseId,
                    $warehouseName,
                ),
            );
        }

        return PdfDownload::streamed(
            $pdfService->detail(
                $service,
                $this->dateFrom,
                $this->dateTo,
                $this->warehouseId,
                $warehouseName,
            ),
        );
    }

    protected function downloadExcel(): mixed
    {
        if (! filled($this->dateFrom) && ! filled($this->dateTo)) {
            Notification::make()->title('حدد الفترة الزمنية')->warning()->send();

            return null;
        }

        $service = app(DailyMovementReportService::class);
        $excelService = app(MarketExcelService::class);
        $warehouseName = filled($this->warehouseId)
            ? Warehouse::query()->whereKey($this->warehouseId)->value('name')
            : null;

        if ($this->activeTab === 'summary') {
            return $excelService->dailyMovementSummary(
                $service,
                $this->dateFrom,
                $this->dateTo,
                $this->warehouseId,
                $warehouseName,
            );
        }

        return $excelService->dailyMovementDetail(
            $service,
            $this->dateFrom,
            $this->dateTo,
            $this->warehouseId,
            $warehouseName,
        );
    }
}
