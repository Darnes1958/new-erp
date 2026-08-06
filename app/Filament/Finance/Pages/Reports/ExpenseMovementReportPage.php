<?php

namespace App\Filament\Finance\Pages\Reports;

use App\Filament\Finance\Pages\Reports\Concerns\InteractsWithFinanceMovementReportExports;
use App\Filament\Finance\Support\FinanceNavigationGroup;
use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Models\ExpenseType;
use App\Services\Excel\FinanceExcelService;
use App\Services\Finance\FinanceMovementReportService;
use App\Services\Pdf\FinanceMovementPdfService;
use App\Support\PdfDownload;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
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
use Illuminate\Support\Facades\Auth;

class ExpenseMovementReportPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithFinanceMovementReportExports;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'حركة مصروفات';

    protected static ?string $title = 'حركة مصروفات';

    protected static ?string $slug = 'expense-movement-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = FinanceNavigationGroup::Expenses;

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.finance.pages.reports.movement-report';

    protected ?string $heading = '';

    public ?int $expenseTypeId = null;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog
            || $user?->can('ادخال مصروفات')
            || $user?->can('تقارير مصروفات');
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
            'expenseTypeId' => $this->expenseTypeId,
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
                        Select::make('expenseTypeId')
                            ->columnSpan(3)
                            ->hiddenLabel()
                            ->prefix('نوع المصروفات')
                            ->options(fn (): array => ExpenseType::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->live(),
                        DatePicker::make('dateFrom')
                            ->columnSpan(2)
                            ->hiddenLabel()
                            ->prefix('من')
                            ->live(),
                        DatePicker::make('dateTo')
                            ->columnSpan(2)
                            ->hiddenLabel()
                            ->prefix('إلي')
                            ->live(),
                        Actions::make([
                            InstallmentListPrintActions::compactPrint('print', fn () => $this->downloadPdf()),
                            InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadExcel()),
                        ])
                            ->columnSpan(1)
                            ->extraAttributes(['class' => 'finance-compact-exports']),
                    ])
                    ->columns(8),
            ])
            ->extraAttributes(['class' => 'gap-y-2']);
    }

    public function updatedExpenseTypeId(): void
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
        $service = app(FinanceMovementReportService::class);

        return $table
            ->query(fn (): Builder => $this->buildReportQuery())
            ->columns([
                TextColumn::make('expense_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('payment_source')
                    ->label('المصرف / الخزينة')
                    ->state(fn ($record): string => $service->expensePaymentSourceLabel($record))
                    ->color(fn ($record): string => $record->bank_account_id ? 'info' : 'success'),
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->numeric(3)
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('')
                            ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ','),
                    ),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(40),
            ])
            ->defaultSort(fn ($query) => $query->orderBy('expense_date')->orderBy('id'))
            ->emptyStateHeading('لا توجد بيانات')
            ->paginated([10, 25, 50, 100])
            ->striped();
    }

    protected function buildReportQuery(): Builder
    {
        return app(FinanceMovementReportService::class)->expensesQuery(
            $this->expenseTypeId,
            $this->dateFrom,
            $this->dateTo,
        );
    }

    protected function validateReportFilters(): bool
    {
        return true;
    }

    protected function exportSortColumn(): string
    {
        return 'expense_date';
    }

    protected function downloadPdf(): mixed
    {
        $rows = $this->exportRows('لا توجد بيانات للطباعة');

        if ($rows === null) {
            return null;
        }

        $expenseTypeName = ExpenseType::query()->find($this->expenseTypeId)?->name ?? 'كل الأنواع';

        return PdfDownload::streamed(
            app(FinanceMovementPdfService::class)->expenseMovement(
                $rows,
                $expenseTypeName,
                $this->dateFrom,
                $this->dateTo,
            ),
        );
    }

    protected function downloadExcel(): mixed
    {
        $rows = $this->exportRows();

        if ($rows === null) {
            return null;
        }

        $expenseTypeName = ExpenseType::query()->find($this->expenseTypeId)?->name ?? 'كل الأنواع';
        $period = collect([$this->dateFrom, $this->dateTo])->filter()->implode(' — ');

        return app(FinanceExcelService::class)->movementReport(
            rows: $rows,
            reportTitle: 'حركة مصروفات : '.$expenseTypeName,
            kind: 'expense',
            subtitle: filled($period) ? 'الفترة : '.$period : null,
            filename: 'expense-movement.xlsx',
        );
    }
}
