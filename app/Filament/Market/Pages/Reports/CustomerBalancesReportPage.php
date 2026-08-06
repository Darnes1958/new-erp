<?php

namespace App\Filament\Market\Pages\Reports;

use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Filament\Market\Pages\Reports\Concerns\InteractsWithMarketReportExports;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Services\Excel\MarketExcelService;
use App\Services\Market\CustomerLedgerReportService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CustomerBalancesReportPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithMarketReportExports;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'أرصدة الزبائن';

    protected static ?string $title = 'أرصدة الزبائن';

    protected static ?string $slug = 'customer-balances-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::CustomersSuppliers;

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.market.pages.reports.customer-report';

    protected ?string $heading = '';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public bool $includeZero = false;

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
        $this->dateTo = now()->toDateString();

        $this->filtersForm->fill([
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
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
                        DatePicker::make('dateFrom')
                            ->columnSpan(2)
                            ->label('من تاريخ')
                            ->live(),
                        DatePicker::make('dateTo')
                            ->columnSpan(2)
                            ->label('إلي تاريخ')
                            ->live(),
                        Checkbox::make('includeZero')
                            ->columnSpan(2)
                            ->label('إظهار الأرصدة الصفرية')
                            ->live()
                            ->afterStateUpdated(function (?bool $state): void {
                                $this->includeZero = (bool) $state;
                                $this->resetTable();
                            }),
                        Actions::make([
                            InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadExcel()),
                        ])
                            ->columnSpan(2)
                            ->extraAttributes(['class' => 'market-compact-exports']),
                    ])
                    ->columns(8),
            ]);
    }

    public function updatedDateFrom(): void
    {
        $this->resetTable();
    }

    public function updatedDateTo(): void
    {
        $this->resetTable();
    }

    public function updatedIncludeZero(): void
    {
        $this->resetTable();
    }

    public function getTableRecordKey(Model|array $record): string
    {
        return (string) $record->customer_id;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->buildReportQuery())
            ->columns([
                TextColumn::make('customer_id')
                    ->label('الرقم الألي')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('اسم الزبون')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('mden')
                    ->label('مدين')
                    ->numeric(3)
                    ->color('danger'),
                TextColumn::make('daen')
                    ->label('دائن')
                    ->numeric(3)
                    ->color('info'),
                TextColumn::make('raseed')
                    ->label('الرصيد')
                    ->numeric(3)
                    ->color(fn ($state): string => (float) $state >= 0 ? 'success' : 'danger'),
            ])
            ->defaultSort(fn ($query) => $query->orderBy('name'))
            ->emptyStateHeading('لا توجد بيانات')
            ->paginated([10, 25, 50, 100])
            ->striped();
    }

    protected function buildReportQuery(): Builder
    {
        return app(CustomerLedgerReportService::class)->balancesQuery(
            $this->dateFrom,
            $this->dateTo,
            $this->includeZero,
        );
    }

    protected function buildExportQuery(): Builder
    {
        return $this->buildReportQuery()->orderBy('name');
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

        $service = app(CustomerLedgerReportService::class);
        $summary = $service->balancesSummary($this->dateFrom, $this->dateTo);
        $title = 'أرصدة الزبائن من تاريخ '.$this->dateFrom.' إلي تاريخ '.$this->dateTo;

        return app(MarketExcelService::class)->customerBalances(
            rows: $rows,
            reportTitle: $title,
            summary: $summary,
        );
    }
}
