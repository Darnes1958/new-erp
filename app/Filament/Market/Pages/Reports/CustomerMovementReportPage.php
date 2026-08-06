<?php

namespace App\Filament\Market\Pages\Reports;

use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Filament\Market\Pages\Reports\Concerns\InteractsWithMarketReportExports;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Services\Excel\MarketExcelService;
use App\Services\Market\CustomerLedgerReportService;
use App\Services\Pdf\CustomerLedgerPdfService;
use App\Support\PdfDownload;
use Filament\Actions\Action;
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

class CustomerMovementReportPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithMarketReportExports;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'حركة زبون';

    protected static ?string $title = 'حركة زبون';

    protected static ?string $slug = 'customer-movement-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::CustomersSuppliers;

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.market.pages.reports.customer-report';

    protected ?string $heading = '';

    public ?int $customerId = null;

    public ?string $dateFrom = null;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('تقارير زبائن');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $this->dateFrom = now()->startOfYear()->toDateString();
        $this->refreshSummaryFields();
    }

    protected function getForms(): array
    {
        return [
            'filtersForm',
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        $service = app(CustomerLedgerReportService::class);

        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Grid::make()
                            ->schema([
                                Select::make('customerId')
                                    ->columnSpan(2)
                                    ->label('الزبون')
                                    ->options(fn (): array => Customer::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    ->live(),
                                DatePicker::make('dateFrom')
                                    ->columnSpan(2)
                                    ->label('من تاريخ')
                                    ->live(),
                                Actions::make([
                                    InstallmentListPrintActions::compactPrint('print', fn () => $this->downloadPdf()),
                                    InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadExcel()),
                                ])
                                    ->columnSpan(2)
                                    ->extraAttributes(['class' => 'market-compact-exports']),
                            ])
                            ->columns(6),
                        Section::make()
                            ->schema([
                                Placeholder::make('lifetime_heading')
                                    ->hiddenLabel()
                                    ->content('الرصيد من بداية المدة')
                                    ->extraAttributes(['class' => 'text-info-600']),
                                Placeholder::make('lifetimeDebit')
                                    ->label('مدين')
                                    ->content(fn (): string => number_format($service->lifetimeTotals($this->customerId)['debit'], 3, '.', ',')),
                                Placeholder::make('lifetimeCredit')
                                    ->label('دائن')
                                    ->content(fn (): string => number_format($service->lifetimeTotals($this->customerId)['credit'], 3, '.', ',')),
                                Placeholder::make('lifetimeBalance')
                                    ->label('الرصيد')
                                    ->content(function () use ($service): HtmlString|string {
                                        $balance = $service->lifetimeTotals($this->customerId)['balance'];

                                        return $balance < 0
                                            ? new HtmlString('<span class="text-danger-600">'.number_format($balance, 3, '.', ',').'</span>')
                                            : new HtmlString('<span class="text-indigo-700">'.number_format($balance, 3, '.', ',').'</span>');
                                    }),
                                Placeholder::make('period_heading')
                                    ->hiddenLabel()
                                    ->content('الرصيد خلال الفترة')
                                    ->extraAttributes(['class' => 'text-info-600']),
                                Placeholder::make('periodDebit')
                                    ->label('مدين')
                                    ->content(fn (): string => number_format($service->periodTotals($this->customerId, $this->dateFrom)['debit'], 3, '.', ',')),
                                Placeholder::make('periodCredit')
                                    ->label('دائن')
                                    ->content(fn (): string => number_format($service->periodTotals($this->customerId, $this->dateFrom)['credit'], 3, '.', ',')),
                                Placeholder::make('periodBalance')
                                    ->label('الرصيد')
                                    ->content(function () use ($service): HtmlString|string {
                                        $balance = $service->periodTotals($this->customerId, $this->dateFrom)['balance'];

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

    public function updatedCustomerId(): void
    {
        $this->resetTable();
        $this->refreshSummaryFields();
    }

    public function updatedDateFrom(): void
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
        $service = app(CustomerLedgerReportService::class);

        return $table
            ->query(fn (): Builder => $this->buildReportQuery())
            ->heading(fn (): string => 'الرصيد السابق: '.number_format($service->openingBalance($this->customerId, $this->dateFrom), 3, '.', ','))
            ->columns([
                TextColumn::make('rep_date')
                    ->label('التاريخ')
                    ->date('Y-m-d'),
                TextColumn::make('id')
                    ->label('الرقم الألي'),
                TextColumn::make('transaction_kind')
                    ->label('البيان')
                    ->formatStateUsing(fn ($state): string => $service->transactionKindLabel((int) $state)),
                TextColumn::make('payment_method_name')
                    ->label('طريقة الدفع'),
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
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(40),
            ])
            ->recordActions([
                Action::make('viewInvoice')
                    ->label('عرض')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->visible(fn (CustomerLedgerEntry $record): bool => (int) $record->transaction_kind === 7)
                    ->url(fn (CustomerLedgerEntry $record): string => route('pdf.sales-invoice', ['salesInvoice' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading(fn (): string => filled($this->customerId) ? 'لا توجد بيانات' : 'اختر الزبون لعرض الحركة')
            ->paginated([10, 25, 50, 100])
            ->striped();
    }

    protected function buildReportQuery(): Builder
    {
        if (! filled($this->customerId)) {
            return CustomerLedgerEntry::query()->whereRaw('1 = 0');
        }

        return app(CustomerLedgerReportService::class)->movementQuery($this->customerId, $this->dateFrom);
    }

    protected function buildExportQuery(): Builder
    {
        return $this->buildReportQuery();
    }

    protected function validateReportFilters(): bool
    {
        if (! filled($this->customerId)) {
            Notification::make()->title('اختر الزبون أولاً')->warning()->send();

            return false;
        }

        if (! filled($this->dateFrom)) {
            Notification::make()->title('حدد تاريخ البداية')->warning()->send();

            return false;
        }

        return true;
    }

    protected function downloadPdf(): mixed
    {
        $rows = $this->exportRows('لا توجد بيانات للطباعة');

        if ($rows === null) {
            return null;
        }

        $service = app(CustomerLedgerReportService::class);
        $customer = $service->resolveCustomer($this->customerId);

        if (! $customer) {
            return null;
        }

        return PdfDownload::streamed(
            app(CustomerLedgerPdfService::class)->customerMovement(
                $customer,
                $rows,
                $this->dateFrom,
                $service->openingBalance($this->customerId, $this->dateFrom),
                $service->periodTotals($this->customerId, $this->dateFrom),
            ),
        );
    }

    protected function downloadExcel(): mixed
    {
        $rows = $this->exportRows();

        if ($rows === null) {
            return null;
        }

        $service = app(CustomerLedgerReportService::class);
        $customer = $service->resolveCustomer($this->customerId);
        $openingBalance = $service->openingBalance($this->customerId, $this->dateFrom);
        $rows = $service->attachRunningBalance($rows, $openingBalance);

        return app(MarketExcelService::class)->customerMovement(
            rows: $rows,
            customerName: $customer?->name ?? '',
            dateFrom: $this->dateFrom ?? '',
            openingBalance: $openingBalance,
            periodTotals: $service->periodTotals($this->customerId, $this->dateFrom),
        );
    }

    protected function refreshSummaryFields(): void
    {
        $this->filtersForm->fill([
            'customerId' => $this->customerId,
            'dateFrom' => $this->dateFrom,
        ]);
    }
}
