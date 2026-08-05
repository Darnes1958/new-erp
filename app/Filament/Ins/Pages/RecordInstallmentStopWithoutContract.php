<?php

namespace App\Filament\Ins\Pages;

use App\Filament\Ins\Resources\InstallmentStopsWithoutContract\InstallmentStopWithoutContractResource;
use App\Models\InstallmentStopWithoutContract;
use App\Models\PayrollBank;
use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Filament\Ins\Support\InstallmentStopWithoutContractReportTitle;
use App\Services\Installments\InstallmentStopWithoutContractReportService;
use App\Services\Pdf\InstallmentStopWithoutContractPdfService;
use App\Support\PdfDownload;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class RecordInstallmentStopWithoutContract extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'إيقاف خصم بدون عقد';

    protected static ?string $title = 'إيقاف خصم بدون عقد';

    protected static ?string $slug = 'installment-stop-without-contract';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|\UnitEnum|null $navigationGroup = 'خصومات ومدفوعات';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.ins.pages.record-installment-stop-without-contract';

    protected ?string $heading = '';

    #[Url(as: 'tab')]
    public string $activeTab = 'register';

    public ?int $payrollBankId = null;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public static function getNavigationBadge(): ?string
    {
        return (string) InstallmentStopWithoutContract::query()->count();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('ادخال عقود') || $user?->can('تعديل عقود');
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('إضافة')
                ->icon(Heroicon::OutlinedPlus)
                ->color('primary')
                ->url(fn (): string => InstallmentStopWithoutContractResource::getUrl('create'))
                ->visible(fn (): bool => $this->activeTab === 'register'),
        ];
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
                        ->options(fn (): array => PayrollBank::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->live(),
                    DatePicker::make('dateFrom')
                        ->inlineLabel()
                        ->label('من')
                        ->live(),
                    DatePicker::make('dateTo')
                        ->inlineLabel()
                        ->label('إلي')
                        ->live(),
                ])->columns(4),
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
            ->query(fn (): Builder => InstallmentStopWithoutContract::query())
            ->defaultSort('id', 'desc')
            ->description('استخدم زر «إضافة» أعلاه لإدخال إيقاف جديد.')
            ->emptyStateHeading('لا توجد بيانات')
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable(),
                TextColumn::make('payrollBank.name')
                    ->label('المصرف التجميعي')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('account_number')
                    ->label('رقم الحساب')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('stop_date')
                    ->label('تاريخ الإيقاف')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('المستخدم'),
            ])
            ->recordActions([
                EditAction::make()
                    ->url(fn (InstallmentStopWithoutContract $record): string => InstallmentStopWithoutContractResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ])
            ->striped();
    }

    protected function configureReportTable(Table $table): Table
    {
        $service = app(InstallmentStopWithoutContractReportService::class);

        return $table
            ->query(fn (): Builder => $service->reportQuery(
                $this->payrollBankId,
                $this->dateFrom,
                $this->dateTo,
            ))
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->summarize(
                        Summarizer::make()
                            ->label('العدد')
                            ->using(fn (): int => $service->summary(
                                $this->payrollBankId,
                                $this->dateFrom,
                                $this->dateTo,
                            )['count']),
                    ),
                TextColumn::make('payrollBank.name')
                    ->label('المصرف التجميعي'),
                TextColumn::make('name')
                    ->label('الاسم'),
                TextColumn::make('account_number')
                    ->label('رقم الحساب'),
                TextColumn::make('stop_date')
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
                    ->action(fn (InstallmentStopWithoutContract $record) => $this->downloadIndividualStopPdf($record)),
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
        $service = app(InstallmentStopWithoutContractReportService::class);
        $payrollBank = $service->resolvePayrollBank($this->payrollBankId);

        if (! $payrollBank) {
            Notification::make()
                ->title('اختر المصرف أولاً')
                ->warning()
                ->send();

            return null;
        }

        $rows = $service->reportQuery(
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
            app(InstallmentStopWithoutContractPdfService::class)->collectiveReport($rows, $payrollBank),
        );
    }

    protected function downloadIndividualStopPdf(InstallmentStopWithoutContract $record): mixed
    {
        $record->loadMissing('payrollBank');

        $payrollBank = app(InstallmentStopWithoutContractReportService::class)->payrollBankForRecord($record);

        if (! $payrollBank) {
            Notification::make()
                ->title('لا يمكن تحديد المصرف التجميعي لهذا السجل')
                ->warning()
                ->send();

            return null;
        }

        return PdfDownload::streamed(
            app(InstallmentStopWithoutContractPdfService::class)->individualReport($record, $payrollBank),
        );
    }

    protected function downloadExcelExport(): mixed
    {
        $service = app(InstallmentStopWithoutContractReportService::class);

        $rows = $service->reportQuery(
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

        return app(\App\Services\Excel\InstallmentExcelService::class)
            ->stopsWithoutContractReport(
                $rows,
                $service->filterLines($this->payrollBankId, $this->dateFrom, $this->dateTo, includeDates: false),
                InstallmentStopWithoutContractReportTitle::build($this->dateFrom, $this->dateTo),
            );
    }
}
