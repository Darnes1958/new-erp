<?php

namespace App\Filament\Ins\Pages\Reports;

use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Models\PayrollBank;
use App\Services\Excel\InstallmentExcelService;
use App\Services\Installments\InstallmentCollectedTotalsReportService;
use App\Services\Pdf\InstallmentCollectedTotalsPdfService;
use App\Support\PdfDownload;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
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

class CollectedTotalsReportPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'اجمالي المحصلة';

    protected static ?string $title = 'اجمالي المحصلة';

    protected static ?string $slug = 'collected-totals-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'تقارير';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.ins.pages.reports.collected-totals-report';

    protected ?string $heading = '';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Authz::can('اجمالي المصارف');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $this->dateFrom = Carbon::now()->startOfYear()->toDateString();
        $this->dateTo = now()->toDateString();

        $this->filtersForm->fill([
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
            ->columns(2)
            ->extraAttributes(['class' => 'gap-y-2']);
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
        return InstallmentListPrintActions::compactPrint('print', fn () => $this->downloadPdf());
    }

    public function exportExcelAction(): Action
    {
        return InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadExcel());
    }

    public function table(Table $table): Table
    {
        $service = app(InstallmentCollectedTotalsReportService::class);

        return $table
            ->query(fn (): Builder => $service->reportQuery($this->dateFrom, $this->dateTo))
            ->columns([
                TextColumn::make('name')
                    ->label('المصرف التجميعي')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('collected_count')
                    ->label('عدد الأقساط المحصلة')
                    ->numeric(0)
                    ->getStateUsing(fn (PayrollBank $record): int => $service->collectedCountFor($record))
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format(
                                $service->summary($this->dateFrom, $this->dateTo)['collected_count'],
                                0,
                                '.',
                                ',',
                            )),
                    ),
                TextColumn::make('collected_total')
                    ->label('إجمالي المحصلة')
                    ->numeric(3)
                    ->getStateUsing(fn (PayrollBank $record): float => $service->collectedTotalFor($record))
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format(
                                $service->summary($this->dateFrom, $this->dateTo)['collected_total'],
                                3,
                                '.',
                                ',',
                            )),
                    ),
                TextColumn::make('expected_total')
                    ->label('إجمالي المفترض تحصيلها')
                    ->numeric(3)
                    ->getStateUsing(fn (PayrollBank $record): float => $service->expectedTotalFor($record))
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format(
                                $service->summary($this->dateFrom, $this->dateTo)['expected_total'],
                                3,
                                '.',
                                ',',
                            )),
                    ),
                TextColumn::make('collection_ratio')
                    ->label('نسبة التحصيل')
                    ->getStateUsing(fn (PayrollBank $record): float => $service->collectionRatioFor($record))
                    ->formatStateUsing(fn (float $state): string => number_format($state, 1).' %')
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format(
                                $service->summary($this->dateFrom, $this->dateTo)['collection_ratio'],
                                1,
                                '.',
                                ',',
                            ).' %'),
                    ),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50, 100])
            ->striped()
            ->emptyStateHeading('لا توجد مصارف تجميعية');
    }

    protected function downloadPdf(): mixed
    {
        $context = $this->resolveExportContext('لا توجد بيانات للطباعة');

        if ($context === null) {
            return null;
        }

        return PdfDownload::streamed(
            app(InstallmentCollectedTotalsPdfService::class)->report(
                $context['rows'],
                $context['summary'],
                $context['reportTitle'],
                $this->dateFrom,
                $this->dateTo,
            ),
        );
    }

    protected function downloadExcel(): mixed
    {
        $context = $this->resolveExportContext();

        if ($context === null) {
            return null;
        }

        return app(InstallmentExcelService::class)->collectedTotalsReport(
            $context['rows'],
            $context['reportTitle'],
        );
    }

    /**
     * @return array{
     *     rows: Collection,
     *     summary: array{collected_count: int, collected_total: float, expected_total: float, collection_ratio: float},
     *     reportTitle: string
     * }|null
     */
    protected function resolveExportContext(string $emptyMessage = 'لا توجد بيانات للتصدير'): ?array
    {
        if (! filled($this->dateFrom) || ! filled($this->dateTo)) {
            Notification::make()
                ->title('أدخل تاريخ البداية والنهاية')
                ->warning()
                ->send();

            return null;
        }

        $service = app(InstallmentCollectedTotalsReportService::class);
        $rows = $service->exportRows($this->dateFrom, $this->dateTo);

        if ($rows->isEmpty()) {
            Notification::make()
                ->title($emptyMessage)
                ->warning()
                ->send();

            return null;
        }

        return [
            'rows' => $rows,
            'summary' => $service->summaryFromRows($rows),
            'reportTitle' => $service->reportTitle($this->dateFrom, $this->dateTo),
        ];
    }
}
