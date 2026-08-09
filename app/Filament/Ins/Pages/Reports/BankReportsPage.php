<?php

namespace App\Filament\Ins\Pages\Reports;

use App\Enums\BankReportType;
use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Models\InstallmentBank;
use App\Models\PayrollBank;
use App\Services\Excel\InstallmentExcelService;
use App\Services\Installments\InstallmentBankReportService;
use App\Services\Pdf\InstallmentBankReportPdfService;
use App\Support\PdfDownload;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class BankReportsPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'تقارير عن مصرف';

    protected static ?string $title = 'تقارير عن مصرف';

    protected static ?string $slug = 'bank-reports';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'تقارير';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.ins.pages.reports.bank-reports';

    protected ?string $heading = '';

    public int $filterBy = 2;

    public ?int $installmentBankId = null;

    public ?int $payrollBankId = null;

    public string $reportType = 'all';

    public float $threshold = 5;

    public bool $notPaidOnly = false;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('تقرير عن مصرف');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $this->payrollBankId = PayrollBank::query()->min('id');
        $this->dateFrom = now()->startOfYear()->toDateString();
        $this->dateTo = now()->toDateString();

        $this->filtersForm->fill([
            'filterBy' => $this->filterBy,
            'installmentBankId' => $this->installmentBankId,
            'payrollBankId' => $this->payrollBankId,
            'reportType' => $this->reportType,
            'threshold' => $this->threshold,
            'notPaidOnly' => $this->notPaidOnly,
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
                Grid::make()
                    ->schema([
                        Radio::make('filterBy')
                            ->hiddenLabel()
                            ->inline()
                            ->live()
                            ->options([
                                2 => 'بالتجميعي',
                                1 => 'بالفروع',
                            ])
                            ->columnSpan(2),
                        Select::make('reportType')
                            ->columnSpan(3)
                            ->hiddenLabel()
                            ->prefix('التقرير')
                            ->options(BankReportType::options())
                            ->live(),
                        Select::make('installmentBankId')
                            ->columnSpan(4)
                            ->hiddenLabel()
                            ->prefix('فرع المصرف')
                            ->options(fn (): array => InstallmentBank::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->live()
                            ->visible(fn (): bool => $this->filterBy === 1),
                        Select::make('payrollBankId')
                            ->columnSpan(4)
                            ->hiddenLabel()
                            ->prefix('المصرف التجميعي')
                            ->options(fn (): array => PayrollBank::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->live()
                            ->visible(fn (): bool => $this->filterBy === 2),
                        Actions::make([
                            InstallmentListPrintActions::compactPrint('print', fn () => $this->downloadPdf()),
                            InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadExcel()),
                        ])
                            ->columnSpan(1)
                            ->extraAttributes(['class' => 'ins-compact-exports']),
                    ])
                    ->columns(10),
                Grid::make()
                    ->schema([
                        TextInput::make('threshold')
                            ->columnSpan(3)
                            ->hiddenLabel()
                            ->prefix(fn (): string => $this->selectedReportType()->thresholdLabel())
                            ->numeric()
                            ->live()
                            ->visible(fn (): bool => $this->selectedReportType()->usesThreshold()),
                        Checkbox::make('notPaidOnly')
                            ->label('لم تسدد بعد')
                            ->columnSpan(2)
                            ->live()
                            ->visible(fn (): bool => $this->reportType === BankReportType::Late->value),
                        DatePicker::make('dateFrom')
                            ->columnSpan(2)
                            ->hiddenLabel()
                            ->prefix('من')
                            ->live()
                            ->visible(fn (): bool => $this->selectedReportType()->usesDateRange()),
                        DatePicker::make('dateTo')
                            ->columnSpan(2)
                            ->hiddenLabel()
                            ->prefix('إلي')
                            ->live()
                            ->visible(fn (): bool => $this->selectedReportType()->usesDateRange()),
                    ])
                    ->columns(7)
                    ->visible(fn (): bool => $this->selectedReportType()->usesThreshold()
                        || $this->selectedReportType()->usesDateRange()),
            ])
            ->extraAttributes(['class' => 'gap-y-2']);
    }

    public function updatedReportType(): void
    {
        $type = $this->selectedReportType();

        if ($type->usesThreshold()) {
            $this->threshold = $type->defaultThreshold();
            $this->filtersForm->fill(['threshold' => $this->threshold]);
        }

        if (! $type->usesDateRange()) {
            $this->notPaidOnly = false;
            $this->filtersForm->fill(['notPaidOnly' => false]);
        }

        $this->resetTable();
    }

    public function updatedFilterBy(): void
    {
        $this->resetTable();
    }

    public function updatedPayrollBankId(): void
    {
        $this->resetTable();
    }

    public function updatedInstallmentBankId(): void
    {
        $this->resetTable();
    }

    public function updatedThreshold(): void
    {
        $this->resetTable();
    }

    public function updatedNotPaidOnly(): void
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

    public function table(Table $table): Table
    {
        if ($this->reportType === BankReportType::Collected->value) {
            return $this->configureCollectedTable($table);
        }

        return $this->configureContractsTable($table);
    }

    protected function configureContractsTable(Table $table): Table
    {
        $service = app(InstallmentBankReportService::class);
        $type = $this->selectedReportType();

        return $table
            ->query(function () use ($service, $type): Builder {
                if ($type === BankReportType::Uncollected) {
                    return $service->uncollectedContractsQuery(
                        $this->filterBy,
                        $this->installmentBankId,
                        $this->payrollBankId,
                        $this->dateFrom,
                        $this->dateTo,
                    );
                }

                return $service->contractsReportQuery(
                    $type,
                    $this->filterBy,
                    $this->installmentBankId,
                    $this->payrollBankId,
                    $this->threshold,
                    $this->notPaidOnly,
                );
            })
            ->columns([
                TextColumn::make('id')
                    ->label('رقم العقد')
                    ->sortable()
                    ->summarize(
                        Summarizer::make()
                            ->label('العدد')
                            ->using(fn (): int => $service->contractsSummary(
                                $type,
                                $this->filterBy,
                                $this->installmentBankId,
                                $this->payrollBankId,
                                $this->threshold,
                                $this->notPaidOnly,
                                $this->dateFrom,
                                $this->dateTo,
                            )['count']),
                    ),
                TextColumn::make('customer.name')
                    ->label('الاسم')
                    ->searchable(),
                TextColumn::make('bank_account_number')
                    ->label('رقم الحساب')
                    ->searchable()
                    ->visible(fn (): bool => $type !== BankReportType::Uncollected),
                TextColumn::make('contract_total')
                    ->label('اجمالي العقد')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format($service->contractsSummary(
                                $type,
                                $this->filterBy,
                                $this->installmentBankId,
                                $this->payrollBankId,
                                $this->threshold,
                                $this->notPaidOnly,
                                $this->dateFrom,
                                $this->dateTo,
                            )['contract_total'], 3, '.', ',')),
                    ),
                TextColumn::make('installment_amount')
                    ->label('القسط')
                    ->numeric(3),
                TextColumn::make('total_paid')
                    ->label('المسدد')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format($service->contractsSummary(
                                $type,
                                $this->filterBy,
                                $this->installmentBankId,
                                $this->payrollBankId,
                                $this->threshold,
                                $this->notPaidOnly,
                                $this->dateFrom,
                                $this->dateTo,
                            )['total_paid'], 3, '.', ',')),
                    ),
                TextColumn::make('balance')
                    ->label('الرصيد')
                    ->numeric(3)
                    ->visible(fn (): bool => $type !== BankReportType::Late)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format($service->contractsSummary(
                                $type,
                                $this->filterBy,
                                $this->installmentBankId,
                                $this->payrollBankId,
                                $this->threshold,
                                $this->notPaidOnly,
                                $this->dateFrom,
                                $this->dateTo,
                            )['balance'], 3, '.', ',')),
                    ),
                TextColumn::make('late_amount')
                    ->label('المتأخرة')
                    ->formatStateUsing(fn ($state): string => (string) (int) $state)
                    ->visible(fn (): bool => $type === BankReportType::Late)
                    ->color('danger'),
                TextColumn::make('last_deduction_month')
                    ->label($type === BankReportType::Late ? 'ت.آخر قسط' : 'تاريخ آخر خصم')
                    ->date('Y-m-d')
                    ->visible(fn (): bool => in_array($type, [BankReportType::Late, BankReportType::Uncollected], true))
                    ->color(fn (): string => $type === BankReportType::Late ? 'danger' : 'info'),
                TextColumn::make('contract_start')
                    ->label('تاريخ العقد')
                    ->date('Y-m-d')
                    ->visible(fn (): bool => $type === BankReportType::Late)
                    ->color('info'),
            ])
            ->defaultSort('id')
            ->paginated([10, 25, 50, 100])
            ->striped();
    }

    protected function configureCollectedTable(Table $table): Table
    {
        $service = app(InstallmentBankReportService::class);

        return $table
            ->query(fn (): Builder => $service->collectedDeductionsQuery(
                $this->filterBy,
                $this->installmentBankId,
                $this->payrollBankId,
                $this->dateFrom,
                $this->dateTo,
            ))
            ->columns([
                TextColumn::make('installment_contract_id')
                    ->label('رقم العقد')
                    ->sortable()
                    ->summarize(
                        Summarizer::make()
                            ->label('العدد')
                            ->using(fn (): int => $service->collectedSummary(
                                $this->filterBy,
                                $this->installmentBankId,
                                $this->payrollBankId,
                                $this->dateFrom,
                                $this->dateTo,
                            )['count']),
                    ),
                TextColumn::make('installmentContract.customer.name')
                    ->label('الاسم'),
                TextColumn::make('installmentContract.contract_total')
                    ->label('اجمالي العقد')
                    ->numeric(3),
                TextColumn::make('installmentContract.installment_amount')
                    ->label('القسط')
                    ->numeric(3),
                TextColumn::make('installmentContract.total_paid')
                    ->label('المسدد')
                    ->numeric(3),
                TextColumn::make('deduction_date')
                    ->label('تاريخ الخصم')
                    ->date('Y-m-d')
                    ->color('info'),
                TextColumn::make('deducted_amount')
                    ->label('الخصم')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format($service->collectedSummary(
                                $this->filterBy,
                                $this->installmentBankId,
                                $this->payrollBankId,
                                $this->dateFrom,
                                $this->dateTo,
                            )['deducted_amount'], 3, '.', ',')),
                    ),
            ])
            ->defaultSort('deduction_date')
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
            app(InstallmentBankReportPdfService::class)->report(
                $context['type'],
                $context['rows'],
                $context['payrollBank'],
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

        return app(InstallmentExcelService::class)->bankReport(
            $context['rows'],
            $context['type'],
            $context['filterLines'],
            $context['reportTitle'],
        );
    }

    /**
     * @return array{
     *     rows: \Illuminate\Support\Collection,
     *     type: BankReportType,
     *     payrollBank: PayrollBank,
     *     reportTitle: string,
     *     filterLines: array<int, string>
     * }|null
     */
    protected function resolveExportContext(string $emptyMessage = 'لا توجد بيانات للتصدير'): ?array
    {
        $service = app(InstallmentBankReportService::class);
        $type = $this->selectedReportType();
        $payrollBank = $service->resolvePayrollBank(
            $this->filterBy,
            $this->installmentBankId,
            $this->payrollBankId,
        );

        if (! $payrollBank) {
            Notification::make()
                ->title('اختر المصرف أولاً')
                ->warning()
                ->send();

            return null;
        }

        if ($type->usesDateRange() && (! filled($this->dateFrom) || ! filled($this->dateTo))) {
            Notification::make()
                ->title('حدد فترة التاريخ')
                ->warning()
                ->send();

            return null;
        }

        $rows = match ($type) {
            BankReportType::Collected => $service->collectedDeductionsQuery(
                $this->filterBy,
                $this->installmentBankId,
                $this->payrollBankId,
                $this->dateFrom,
                $this->dateTo,
            )->get(),
            BankReportType::Uncollected => $service->uncollectedContractsQuery(
                $this->filterBy,
                $this->installmentBankId,
                $this->payrollBankId,
                $this->dateFrom,
                $this->dateTo,
            )->get(),
            default => $service->contractsReportQuery(
                $type,
                $this->filterBy,
                $this->installmentBankId,
                $this->payrollBankId,
                $this->threshold,
                $this->notPaidOnly,
            )->get(),
        };

        if ($rows->isEmpty()) {
            Notification::make()
                ->title($emptyMessage)
                ->warning()
                ->send();

            return null;
        }

        if ($type !== BankReportType::Collected) {
            $rows = $rows->sortBy([
                ['id', 'asc'],
            ])->values();
        } else {
            $rows = $rows->sortBy([
                ['deduction_date', 'asc'],
                ['id', 'asc'],
            ])->values();
        }

        return [
            'rows' => $rows,
            'type' => $type,
            'payrollBank' => $payrollBank,
            'reportTitle' => $type->pdfTitle($this->dateFrom, $this->dateTo),
            'filterLines' => $service->filterLines(
                $type,
                $payrollBank,
                $this->threshold,
                $this->notPaidOnly,
            ),
        ];
    }

    protected function selectedReportType(): BankReportType
    {
        return BankReportType::from($this->reportType);
    }
}
