<?php

namespace App\Filament\Market\Pages\Reports;

use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Filament\Market\Pages\Reports\Concerns\InteractsWithPaymentAccountMovementReport;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Services\Market\PaymentAccountLedgerReportService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class BankAccountMovementReportPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithPaymentAccountMovementReport;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'حركة مصرف';

    protected static ?string $title = 'حركة مصرف';

    protected static ?string $slug = 'bank-account-movement-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::BanksAndCashBoxes;

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.market.pages.reports.customer-report';

    protected ?string $heading = '';

    public ?int $bankAccountId = null;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog
            || $user?->can('ادخال خزائن')
            || $user?->can('ادخال مصارف')
            || $user?->can('تقارير مصارف');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $this->dateFrom ??= now()->startOfYear()->toDateString();
        $this->dateTo ??= now()->toDateString();
        $this->refreshSummaryFields();
    }

    protected function getForms(): array
    {
        return ['filtersForm'];
    }

    public function filtersForm(Schema $schema): Schema
    {
        $service = app(PaymentAccountLedgerReportService::class);

        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Grid::make()
                            ->schema([
                                Select::make('bankAccountId')
                                    ->columnSpan(2)
                                    ->label('الحساب')
                                    ->options(fn (): array => BankAccount::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    ->live(),
                                DatePicker::make('dateFrom')
                                    ->columnSpan(2)
                                    ->label('من تاريخ')
                                    ->live(),
                                DatePicker::make('dateTo')
                                    ->columnSpan(2)
                                    ->label('إلى تاريخ')
                                    ->live(),
                                Actions::make([
                                    InstallmentListPrintActions::compactPrint('print', fn () => $this->downloadPaymentAccountPdf()),
                                    InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadPaymentAccountExcel()),
                                ])
                                    ->columnSpan(2)
                                    ->extraAttributes(['class' => 'market-compact-exports']),
                            ])
                            ->columns(8),
                        Section::make()
                            ->schema([
                                Placeholder::make('period_heading')
                                    ->hiddenLabel()
                                    ->content('الرصيد خلال الفترة')
                                    ->extraAttributes(['class' => 'text-info-600']),
                                Placeholder::make('periodDebit')
                                    ->label('مدين')
                                    ->content(fn (): string => number_format($service->bankAccountPeriodTotals($this->bankAccountId, $this->dateFrom, $this->dateTo)['debit'], 3, '.', ',')),
                                Placeholder::make('periodCredit')
                                    ->label('دائن')
                                    ->content(fn (): string => number_format($service->bankAccountPeriodTotals($this->bankAccountId, $this->dateFrom, $this->dateTo)['credit'], 3, '.', ',')),
                                Placeholder::make('periodBalance')
                                    ->label('الرصيد')
                                    ->content(function () use ($service): HtmlString|string {
                                        $balance = $service->bankAccountPeriodTotals($this->bankAccountId, $this->dateFrom, $this->dateTo)['balance'];

                                        return $balance < 0
                                            ? new HtmlString('<span class="text-danger-600">'.number_format($balance, 3, '.', ',').'</span>')
                                            : new HtmlString('<span class="text-indigo-700">'.number_format($balance, 3, '.', ',').'</span>');
                                    }),
                            ])
                            ->columns(4),
                    ])
                    ->columns(1),
            ]);
    }

    public function updatedBankAccountId(): void
    {
        $this->resetTable();
        $this->refreshSummaryFields();
    }

    public function updatedDateFrom(): void
    {
        $this->resetTable();
        $this->refreshSummaryFields();
    }

    public function updatedDateTo(): void
    {
        $this->resetTable();
        $this->refreshSummaryFields();
    }

    public function getTableRecordKey(Model|array $record): string
    {
        return (string) $record->idd;
    }

    public function table(Table $table): Table
    {
        $service = app(PaymentAccountLedgerReportService::class);

        return $table
            ->query(fn (): Builder => $this->buildReportQuery())
            ->heading(fn (): string => 'الرصيد السابق: '.number_format($service->bankAccountOpeningBalance($this->bankAccountId, $this->dateFrom), 3, '.', ','))
            ->columns([
                TextColumn::make('transaction_kind')
                    ->label('البيان')
                    ->formatStateUsing(fn ($state): string => $service->transactionKindLabel((int) $state)),
                TextColumn::make('party_name')
                    ->label('الطرف'),
                TextColumn::make('rep_date')
                    ->label('التاريخ')
                    ->date('Y-m-d'),
                TextColumn::make('mden')
                    ->label('مدين')
                    ->numeric(3)
                    ->color('danger'),
                TextColumn::make('daen')
                    ->label('دائن')
                    ->numeric(3)
                    ->color('info'),
                TextColumn::make('running_balance')
                    ->label('الرصيد')
                    ->numeric(3),
                TextColumn::make('document_no')
                    ->label('رقم المستند'),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(40),
            ])
            ->emptyStateHeading(fn (): string => filled($this->bankAccountId) ? 'لا توجد بيانات' : 'اختر الحساب لعرض الحركة')
            ->paginated([10, 25, 50, 100])
            ->striped();
    }

    protected function buildReportQuery(): Builder
    {
        if (! filled($this->bankAccountId)) {
            return BankAccountLedgerEntry::query()->whereRaw('1 = 0');
        }

        return app(PaymentAccountLedgerReportService::class)
            ->bankAccountMovementQuery($this->bankAccountId, $this->dateFrom, $this->dateTo);
    }

    protected function accountIdProperty(): string
    {
        return 'bankAccountId';
    }

    protected function accountName(?int $accountId): ?string
    {
        return filled($accountId) ? BankAccount::query()->whereKey($accountId)->value('name') : null;
    }

    protected function movementQuery(PaymentAccountLedgerReportService $service): Builder
    {
        return $service->bankAccountMovementQuery($this->bankAccountId, $this->dateFrom, $this->dateTo);
    }

    protected function openingBalance(PaymentAccountLedgerReportService $service): float
    {
        return $service->bankAccountOpeningBalance($this->bankAccountId, $this->dateFrom);
    }

    protected function periodTotals(PaymentAccountLedgerReportService $service): array
    {
        return $service->bankAccountPeriodTotals($this->bankAccountId, $this->dateFrom, $this->dateTo);
    }

    protected function reportTitle(): string
    {
        return 'حركة مصرف';
    }

    protected function excelReportTitle(): string
    {
        return 'كشف حسابنا المصرفي';
    }

    protected function validateAccountSelected(): bool
    {
        if (! filled($this->bankAccountId)) {
            Notification::make()->title('اختر الحساب أولاً')->warning()->send();

            return false;
        }

        return true;
    }

    protected function refreshSummaryFields(): void
    {
        $this->filtersForm->fill([
            'bankAccountId' => $this->bankAccountId,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ]);
    }
}
