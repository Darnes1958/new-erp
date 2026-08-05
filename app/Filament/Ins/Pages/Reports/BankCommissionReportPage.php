<?php

namespace App\Filament\Ins\Pages\Reports;

use App\Services\Excel\InstallmentExcelService;
use App\Services\Installments\InstallmentBankCommissionReportService;
use App\Services\Pdf\InstallmentBankCommissionPdfService;
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
use Illuminate\Support\Facades\Auth;

class BankCommissionReportPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'عمولة المصارف';

    protected static ?string $title = 'عمولة المصارف';

    protected static ?string $slug = 'bank-commission-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|\UnitEnum|null $navigationGroup = 'تقارير';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.ins.pages.reports.bank-commission-report';

    protected ?string $heading = '';

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
        $service = app(InstallmentBankCommissionReportService::class);

        return $table
            ->query(fn (): Builder => $service->reportQuery($this->dateFrom, $this->dateTo))
            ->columns([
                TextColumn::make('bankMain.name')
                    ->label('المصرف الأم')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
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
                                $service->summary($this->dateFrom, $this->dateTo)['installments_count'],
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
                                $service->summary($this->dateFrom, $this->dateTo)['collected_total'],
                                3,
                                '.',
                                ',',
                            )),
                    ),
                TextColumn::make('commission')
                    ->label('العمولة')
                    ->state(fn ($record): float => $service->commissionFor($record))
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format(
                                $service->summary($this->dateFrom, $this->dateTo)['commission_total'],
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
            app(InstallmentBankCommissionPdfService::class)->report(
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

        return app(InstallmentExcelService::class)->bankCommissionReport(
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
        if (! filled($this->dateFrom) || ! filled($this->dateTo)) {
            Notification::make()
                ->title('أدخل تاريخ البداية والنهاية')
                ->warning()
                ->send();

            return null;
        }

        $service = app(InstallmentBankCommissionReportService::class);
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
            'summary' => $service->summary($this->dateFrom, $this->dateTo),
            'reportTitle' => $service->reportTitle($this->dateFrom, $this->dateTo),
        ];
    }
}
