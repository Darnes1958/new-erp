<?php

namespace App\Filament\Ins\Pages;

use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Models\InstallmentContract;
use App\Models\PayrollBank;
use App\Services\Excel\InstallmentExcelService;
use App\Services\Installments\InstallmentStopReportService;
use App\Services\Installments\InstallmentStopService;
use App\Services\Pdf\InstallmentStopPdfService;
use App\Support\PdfDownload;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class RecordInstallmentStop extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'إيقاف الخصم';

    protected static ?string $title = 'إيقاف الخصم';

    protected static ?string $slug = 'record-installment-stop';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|\UnitEnum|null $navigationGroup = 'خصومات ومدفوعات';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.ins.pages.record-installment-stop';

    protected ?string $heading = '';

    #[Url(as: 'tab')]
    public string $activeTab = 'register';

    public ?int $payrollBankId = null;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public static function getNavigationBadge(): ?string
    {
        return (string) app(InstallmentStopService::class)->eligibleCount();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return \App\Support\Authz::can('ايقاف الخصم', 'تقرير ايقاف الخصم', 'ادخال عقود', 'تعديل عقود');
    }

    public static function reportTabUrl(): string
    {
        return static::getUrl(['tab' => 'report']);
    }

    public function mount(): void
    {
        if (! in_array($this->activeTab, ['register', 'report'], true)) {
            $this->activeTab = 'register';
        }

        $this->dateFrom = Carbon::now()->startOfYear()->toDateString();
        $this->dateTo = now()->toDateString();

        $this->reportForm->fill([
            'payrollBankId' => $this->payrollBankId,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ]);
    }

    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['register', 'report'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->updatedActiveTab();
    }

    protected function getForms(): array
    {
        return [
            'reportForm',
        ];
    }

    public function reportForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Select::make('payrollBankId')
                        ->columnSpan(2)
                        ->inlineLabel()
                        ->label('المصرف التجميعي')
                        ->placeholder('الكل')
                        ->options(fn (): array => PayrollBank::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state): void {
                            $this->payrollBankId = $state ? (int) $state : null;
                            $this->resetTable();
                        }),
                    DatePicker::make('dateFrom')
                        ->inlineLabel()
                        ->label('من')
                        ->live()
                        ->visible(fn (): bool => $this->activeTab === 'report')
                        ->afterStateUpdated(function ($state): void {
                            $this->dateFrom = $state;
                            $this->resetTable();
                        }),
                    DatePicker::make('dateTo')
                        ->inlineLabel()
                        ->label('إلي')
                        ->live()
                        ->visible(fn (): bool => $this->activeTab === 'report')
                        ->afterStateUpdated(function ($state): void {
                            $this->dateTo = $state;
                            $this->resetTable();
                        }),
                ])->columns(5),
            ]);
    }

    public function table(Table $table): Table
    {
        if ($this->activeTab === 'report') {
            return $this->configureReportTable($table);
        }

        return $this->configureRegisterTable($table);
    }

    protected function configureRegisterTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(InstallmentStopService::class)->eligibleContractsQuery(
                $this->payrollBankId,
            ))
            ->description('حدّد العقود المراد إيقافها عبر مربعات الاختيار على يمين الجدول، ثم اختر «إيقاف» من شريط الأدوات.')
            ->emptyStateHeading('لا توجد عقود منتهية لإيقافها')
            ->columns([
                TextColumn::make('id')
                    ->label('رقم العقد')
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('bank_account_number')
                    ->label('رقم الحساب')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('bank_name')
                    ->label('المصرف')
                    ->getStateUsing(fn (InstallmentContract $record): string => $record->payrollBank?->name
                        ?? $record->installmentBank?->name
                        ?? '—'),
                TextColumn::make('balance')
                    ->label('الرصيد')
                    ->numeric(3),
            ])
            ->toolbarActions([
                BulkAction::make('stop')
                    ->label('إيقاف')
                    ->color('danger')
                    ->deselectRecordsAfterCompletion()
                    ->schema([
                        DatePicker::make('stop_date')
                            ->label('تاريخ الإيقاف')
                            ->default(now()->toDateString())
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $count = app(InstallmentStopService::class)->stopMany(
                            $records,
                            Carbon::parse($data['stop_date'] ?? now()->toDateString()),
                        );

                        Notification::make()
                            ->title("تم إيقاف {$count} عقد")
                            ->success()
                            ->send();

                        $this->resetTable();
                    }),
            ])
            ->striped();
    }

    protected function configureReportTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(InstallmentStopReportService::class)->stoppedContractsQuery(
                2,
                null,
                $this->payrollBankId,
                $this->dateFrom,
                $this->dateTo,
            ))
            ->columns([
                TextColumn::make('id')
                    ->label('رقم العقد'),
                TextColumn::make('bank_account_number')
                    ->label('رقم الحساب')
                    ->summarize(
                        Summarizer::make()
                            ->label('العدد')
                            ->using(function (): int {
                                $summary = app(InstallmentStopReportService::class)->bankSummary(
                                    2,
                                    null,
                                    $this->payrollBankId,
                                    $this->dateFrom,
                                    $this->dateTo,
                                );

                                return $summary['count'];
                            }),
                    ),
                TextColumn::make('customer.name')
                    ->label('الاسم'),
                TextColumn::make('contract_total')
                    ->label('اجمالي العقد')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(function (): string {
                                $summary = app(InstallmentStopReportService::class)->bankSummary(
                                    2,
                                    null,
                                    $this->payrollBankId,
                                    $this->dateFrom,
                                    $this->dateTo,
                                );

                                return number_format($summary['contract_total'], 3, '.', ',');
                            }),
                    ),
                TextColumn::make('installment_amount')
                    ->label('القسط')
                    ->numeric(3),
                TextColumn::make('total_paid')
                    ->label('المسدد')
                    ->numeric(3),
                TextColumn::make('balance')
                    ->label('الرصيد')
                    ->numeric(3),
                TextColumn::make('stop.stop_date')
                    ->label('تاريخ الإيقاف')
                    ->date('Y-m-d')
                    ->color('info'),
            ])
            ->recordActions([
                Action::make('printIndividual')
                    ->hiddenLabel()
                    ->icon(Heroicon::OutlinedPrinter)
                    ->iconButton()
                    ->color('info')
                    ->action(fn (InstallmentContract $record) => $this->downloadIndividualStopPdf($record)),
            ])
            ->paginated([10, 25, 50, 100]);
    }

    public function printAction(): Action
    {
        return InstallmentListPrintActions::compactPrint('print', fn () => $this->downloadCollectiveStopPdf());
    }

    public function exportExcelAction(): Action
    {
        return InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadExcelExport());
    }

    protected function afterActionCalled(Action $action): void
    {
        if ($action->getName() !== 'printIndividual') {
            return;
        }

        $this->defaultTableAction = null;
        $this->defaultTableActionRecord = null;
        $this->defaultTableActionArguments = null;
        $this->flushCachedTableRecords();
    }

    protected function downloadCollectiveStopPdf(): mixed
    {
        $service = app(InstallmentStopReportService::class);
        $payrollBank = $service->resolvePayrollBank(
            2,
            null,
            $this->payrollBankId,
        );

        if (! $payrollBank) {
            Notification::make()
                ->title('اختر المصرف أولاً')
                ->warning()
                ->send();

            return null;
        }

        $rows = $service->stoppedContractsQuery(
            2,
            null,
            $this->payrollBankId,
            $this->dateFrom,
            $this->dateTo,
        )->get();

        if ($rows->isEmpty()) {
            Notification::make()
                ->title('لا توجد بيانات للطباعة')
                ->warning()
                ->send();

            return null;
        }

        return PdfDownload::streamed(
            app(InstallmentStopPdfService::class)->collectiveReport($rows, $payrollBank),
        );
    }

    protected function downloadExcelExport(): mixed
    {
        $service = app(InstallmentStopReportService::class);
        $payrollBank = $service->resolvePayrollBank(
            2,
            null,
            $this->payrollBankId,
        );

        if (! $payrollBank) {
            Notification::make()
                ->title('اختر المصرف أولاً')
                ->warning()
                ->send();

            return null;
        }

        $rows = $service->stoppedContractsQuery(
            2,
            null,
            $this->payrollBankId,
            $this->dateFrom,
            $this->dateTo,
        )->get();

        if ($rows->isEmpty()) {
            Notification::make()
                ->title('لا توجد بيانات للتصدير')
                ->warning()
                ->send();

            return null;
        }

        return app(InstallmentExcelService::class)->installmentStopReport(
            $rows,
            $service->filterLines($payrollBank, $this->dateFrom, $this->dateTo),
        );
    }

    protected function downloadIndividualStopPdf(InstallmentContract $record): mixed
    {
        $record->loadMissing(['customer', 'stop', 'installmentBank.payrollBank']);

        $payrollBank = app(InstallmentStopReportService::class)->payrollBankForContract($record);

        if (! $payrollBank) {
            Notification::make()
                ->title('لا يمكن تحديد المصرف التجميعي لهذا العقد')
                ->warning()
                ->send();

            return null;
        }

        return PdfDownload::streamed(
            app(InstallmentStopPdfService::class)->individualReport($record, $payrollBank),
        );
    }
}
