<?php

namespace App\Filament\Market\Pages\Reports;

use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Filament\Market\Pages\Reports\Concerns\InteractsWithMarketReportExports;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\Warehouse;
use App\Services\Excel\MarketExcelService;
use App\Services\Market\WarehouseStockReportService;
use App\Services\Pdf\WarehouseStockPdfService;
use App\Support\CompanySettings;
use App\Support\PdfDownload;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class WarehouseStockReportPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithMarketReportExports;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'تقرير عن المخزون';

    protected static ?string $title = 'تقرير عن المخزون';

    protected static ?string $slug = 'warehouse-stock-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::WarehousesItems;

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.market.pages.reports.customer-report';

    protected ?string $heading = '';

    public ?int $warehouseId = null;

    public bool $includeZero = false;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('تقارير مخزون');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $this->filtersForm->fill([
            'warehouseId' => $this->warehouseId,
            'includeZero' => $this->includeZero,
        ]);
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
                        Select::make('warehouseId')
                            ->columnSpan(2)
                            ->label('حسب المكان')
                            ->options(fn (): array => Warehouse::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->placeholder('كل الأماكن')
                            ->live()
                            ->afterStateUpdated(function (?int $state): void {
                                $this->warehouseId = $state;
                                $this->resetTable();
                            }),
                        Checkbox::make('includeZero')
                            ->columnSpan(2)
                            ->label('إظهار الأصفار')
                            ->live()
                            ->afterStateUpdated(function (?bool $state): void {
                                $this->includeZero = (bool) $state;
                                $this->resetTable();
                            }),
                        Actions::make([
                            InstallmentListPrintActions::compactPrint('printWithCosts', fn () => $this->downloadPdf(showCosts: true)),
                            \Filament\Actions\Action::make('printWithoutCosts')
                                ->label('طباعة بدون سعر')
                                ->icon('heroicon-o-printer')
                                ->iconButton()
                                ->color('gray')
                                ->tooltip('طباعة بدون سعر الشراء')
                                ->action(fn () => $this->downloadPdf(showCosts: false)),
                            InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadExcel()),
                        ])
                            ->columnSpan(2)
                            ->extraAttributes(['class' => 'market-compact-exports']),
                    ])
                    ->columns(6),
            ]);
    }

    public function getTableRecordKey(Model|array $record): string
    {
        return (string) $record->row_key;
    }

    public function table(Table $table): Table
    {
        $multiWarehouse = CompanySettings::multiWarehouse();
        $canViewBuyPrices = $this->canViewBuyPrices();

        return $table
            ->query(fn (): Builder => $this->buildReportQuery())
            ->columns([
                TextColumn::make('warehouse_name')
                    ->label('المكان')
                    ->sortable()
                    ->searchable()
                    ->visible($multiWarehouse),
                TextColumn::make('item_id')
                    ->label('رقم الصنف')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('item_name')
                    ->label('اسم الصنف')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('total_qty_primary')
                    ->label('الرصيد الكلي')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('total_cost_all')
                    ->label('التكلفة الكلية')
                    ->numeric(3)
                    ->sortable()
                    ->visible($canViewBuyPrices),
                TextColumn::make('warehouse_qty_primary')
                    ->label('رصيد المكان')
                    ->numeric(3)
                    ->sortable()
                    ->visible($multiWarehouse),
                TextColumn::make('avg_unit_cost')
                    ->label('متوسط السعر')
                    ->numeric(3)
                    ->sortable()
                    ->visible($canViewBuyPrices),
                TextColumn::make('catalog_buy_price')
                    ->label('سعر الشراء')
                    ->numeric(3)
                    ->visible($canViewBuyPrices),
                TextColumn::make('warehouse_cost_total')
                    ->label('تكلفة المكان')
                    ->numeric(3)
                    ->visible($canViewBuyPrices)
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->numeric(3),
                    ),
                TextColumn::make('sell_price_primary')
                    ->label('سعر البيع')
                    ->numeric(3)
                    ->sortable(),
            ])
            ->defaultSort('item_name')
            ->emptyStateHeading('لا توجد بيانات')
            ->paginated([10, 25, 50, 100])
            ->striped();
    }

    protected function buildReportQuery(): Builder
    {
        return app(WarehouseStockReportService::class)->reportQuery(
            $this->warehouseId,
            $this->includeZero,
        );
    }

    protected function buildExportQuery(): Builder
    {
        return $this->buildReportQuery();
    }

    protected function validateReportFilters(): bool
    {
        return true;
    }

    protected function downloadExcel(): mixed
    {
        $rows = $this->exportRows();

        if ($rows === null) {
            return null;
        }

        $service = app(WarehouseStockReportService::class);
        $summary = $service->summary($this->warehouseId, $this->includeZero);
        $warehouseName = $this->warehouseId
            ? Warehouse::query()->find($this->warehouseId)?->name
            : null;
        $title = 'تقرير عن المخزون — '.now()->toDateString();
        if ($warehouseName) {
            $title .= ' — '.$warehouseName;
        }

        return app(MarketExcelService::class)->warehouseStock(
            rows: $rows,
            reportTitle: $title,
            summary: $summary,
            warehouseName: $warehouseName,
            showCosts: $this->canViewBuyPrices(),
            multiWarehouse: CompanySettings::multiWarehouse(),
        );
    }

    protected function downloadPdf(bool $showCosts = true): mixed
    {
        if ($showCosts && ! $this->canViewBuyPrices()) {
            $showCosts = false;
        }

        $rows = $this->exportRows();

        if ($rows === null) {
            return null;
        }

        $service = app(WarehouseStockReportService::class);
        $summary = $service->summary($this->warehouseId, $this->includeZero);
        $warehouseName = $this->warehouseId
            ? Warehouse::query()->find($this->warehouseId)?->name
            : null;

        return PdfDownload::streamed(
            app(WarehouseStockPdfService::class)->report(
                rows: $rows,
                summary: $summary,
                warehouseName: $warehouseName,
                showCosts: $showCosts,
                multiWarehouse: CompanySettings::multiWarehouse(),
            ),
        );
    }

    protected function canViewBuyPrices(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('ادخال مشتريات');
    }
}
