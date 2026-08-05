<?php

namespace App\Filament\Ins\Pages\Reports;

use App\Models\Warehouse;
use App\Services\Excel\InstallmentExcelService;
use App\Services\Installments\InstallmentBranchCommissionReportService;
use App\Services\Pdf\InstallmentBranchCommissionPdfService;
use App\Support\PdfDownload;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class BranchCommissionReportPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'عمولة الفروع';

    protected static ?string $title = 'عمولة الفروع';

    protected static ?string $slug = 'branch-commission-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|\UnitEnum|null $navigationGroup = 'تقارير';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.ins.pages.reports.branch-commission-report';

    protected ?string $heading = '';

    public ?int $warehouseId = null;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('اجمالي المصارف');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $this->warehouseId = Warehouse::query()->orderBy('name')->value('id');
        $this->dateFrom = Carbon::now()->startOfYear()->toDateString();
        $this->dateTo = now()->toDateString();

        $this->filtersForm->fill([
            'warehouseId' => $this->warehouseId,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
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
                Select::make('warehouseId')
                    ->hiddenLabel()
                    ->prefix('الفرع')
                    ->options(fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                DatePicker::make('dateFrom')
                    ->hiddenLabel()
                    ->prefix('من')
                    ->live()
                    ->required(),
                DatePicker::make('dateTo')
                    ->hiddenLabel()
                    ->prefix('إلي')
                    ->live()
                    ->required(),
            ])
            ->columns(3)
            ->extraAttributes(['class' => 'gap-y-2']);
    }

    public function updatedWarehouseId(): void
    {
        $this->resetTable();
    }

    public function updatedDateFrom(): void
    {
        $this->resetTable();
    }

    public function updatedDateTo(): void
    {
        $this->resetTable();
    }

    public function printAction(): Action
    {
        return Action::make('print')
            ->label('طباعة')
            ->button()
            ->icon(Heroicon::OutlinedPrinter)
            ->color('info')
            ->action(fn () => $this->downloadPdf());
    }

    public function exportExcelAction(): Action
    {
        return Action::make('exportExcel')
            ->label('Excl')
            ->button()
            ->icon(Heroicon::OutlinedTableCells)
            ->color('success')
            ->action(fn () => $this->downloadExcel());
    }

    public function table(Table $table): Table
    {
        $service = app(InstallmentBranchCommissionReportService::class);

        return $table
            ->query(fn (): Builder => $service->reportQuery($this->warehouseId, $this->dateFrom, $this->dateTo))
            ->columns([
                TextColumn::make('name')
                    ->label('الحساب التجميعي')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('installments_count')
                    ->label('عدد الأقساط المحصلة')
                    ->numeric(0)
                    ->sortable()
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format(
                                $service->summary($this->warehouseId, $this->dateFrom, $this->dateTo)['installments_count'],
                                0,
                                '.',
                                ',',
                            )),
                    ),
                TextColumn::make('collected_total')
                    ->label('اجمالي الأقساط المحصلة')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format(
                                $service->summary($this->warehouseId, $this->dateFrom, $this->dateTo)['collected_total'],
                                3,
                                '.',
                                ',',
                            )),
                    ),
                TextColumn::make('commission')
                    ->label('عمولة المصرف')
                    ->state(fn ($record): float => $service->commissionFor($record))
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format(
                                $service->summary($this->warehouseId, $this->dateFrom, $this->dateTo)['commission_total'],
                                3,
                                '.',
                                ',',
                            )),
                    ),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50, 100])
            ->striped();
    }

    protected function downloadPdf(): mixed
    {
        $context = $this->resolveExportContext('لا توجد بيانات للطباعة');

        if ($context === null) {
            return null;
        }

        return PdfDownload::streamed(
            app(InstallmentBranchCommissionPdfService::class)->report(
                $context['rows'],
                $context['summary'],
                $context['reportTitle'],
            ),
        );
    }

    protected function downloadExcel(): mixed
    {
        $context = $this->resolveExportContext();

        if ($context === null) {
            return null;
        }

        return app(InstallmentExcelService::class)->branchCommissionReport(
            $context['rows'],
            $context['reportTitle'],
        );
    }

    /**
     * @return array{
     *     rows: Collection,
     *     summary: array{installments_count: int, collected_total: float, commission_total: float},
     *     reportTitle: string
     * }|null
     */
    protected function resolveExportContext(string $emptyMessage = 'لا توجد بيانات للتصدير'): ?array
    {
        if (! $this->warehouseId || ! filled($this->dateFrom) || ! filled($this->dateTo)) {
            Notification::make()
                ->title('اختر الفرع وحدد الفترة')
                ->warning()
                ->send();

            return null;
        }

        $service = app(InstallmentBranchCommissionReportService::class);
        $rows = $service->exportRows($this->warehouseId, $this->dateFrom, $this->dateTo);

        if ($rows->isEmpty()) {
            Notification::make()
                ->title($emptyMessage)
                ->warning()
                ->send();

            return null;
        }

        return [
            'rows' => $rows,
            'summary' => $service->summary($this->warehouseId, $this->dateFrom, $this->dateTo),
            'reportTitle' => $service->reportTitle($this->warehouseId, $this->dateFrom, $this->dateTo),
        ];
    }
}
