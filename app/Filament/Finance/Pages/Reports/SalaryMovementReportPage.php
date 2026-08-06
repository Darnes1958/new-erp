<?php

namespace App\Filament\Finance\Pages\Reports;

use App\Enums\SalaryTransactionType;
use App\Filament\Finance\Pages\Reports\Concerns\InteractsWithFinanceMovementReportExports;
use App\Filament\Finance\Support\FinanceNavigationGroup;
use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Models\SalaryProfile;
use App\Services\Excel\FinanceExcelService;
use App\Services\Finance\FinanceMovementReportService;
use App\Services\Pdf\FinanceMovementPdfService;
use App\Support\PdfDownload;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class SalaryMovementReportPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithFinanceMovementReportExports;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'حركة مرتب';

    protected static ?string $title = 'حركة مرتب';

    protected static ?string $slug = 'salary-movement-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = FinanceNavigationGroup::Salaries;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.finance.pages.reports.movement-report';

    protected ?string $heading = '';

    public ?int $salaryProfileId = null;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog
            || $user?->can('مرتبات')
            || $user?->can('تقارير مرتبات');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $this->filtersForm->fill([
            'salaryProfileId' => $this->salaryProfileId,
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
                        Select::make('salaryProfileId')
                            ->columnSpan(3)
                            ->hiddenLabel()
                            ->prefix('الاسم')
                            ->options(fn (): array => SalaryProfile::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->live(),
                        Placeholder::make('balance')
                            ->columnSpan(2)
                            ->label('الرصيد')
                            ->content(function (Get $get): HtmlString|string {
                                $profileId = $get('salaryProfileId');

                                if (! filled($profileId)) {
                                    return '0';
                                }

                                $balance = (float) SalaryProfile::query()->whereKey($profileId)->value('balance');

                                if ($balance < 0) {
                                    return new HtmlString('<span class="text-danger-600">'.$balance.'</span>');
                                }

                                return new HtmlString('<span class="text-indigo-700">'.$balance.'</span>');
                            }),
                        Actions::make([
                            InstallmentListPrintActions::compactPrint('print', fn () => $this->downloadPdf()),
                            InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadExcel()),
                        ])
                            ->columnSpan(1)
                            ->extraAttributes(['class' => 'finance-compact-exports']),
                    ])
                    ->columns(6),
            ])
            ->extraAttributes(['class' => 'gap-y-2']);
    }

    public function updatedSalaryProfileId(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $service = app(FinanceMovementReportService::class);

        return $table
            ->query(fn (): Builder => $this->buildReportQuery())
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('transaction_type')
                    ->label('البيان')
                    ->formatStateUsing(fn (SalaryTransactionType $state): string => $state->getLabel())
                    ->color(fn (SalaryTransactionType $state): string => match ($state) {
                        SalaryTransactionType::Salary => 'success',
                        SalaryTransactionType::Withdrawal => 'danger',
                        SalaryTransactionType::Deduction => 'primary',
                        SalaryTransactionType::Addition => 'info',
                    }),
                TextColumn::make('payment_source')
                    ->label('دفعت من')
                    ->state(fn ($record): string => $service->paymentSourceLabel($record))
                    ->color(fn ($record): string => $record->bank_account_id ? 'info' : 'success'),
                TextColumn::make('period_month')
                    ->label('عن شهر')
                    ->formatStateUsing(fn (?string $state): string => $service->formatPeriodMonth($state)),
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
            ->defaultSort(fn ($query) => $query->orderBy('transaction_date')->orderBy('id'))
            ->emptyStateHeading('لا توجد بيانات')
            ->paginated([10, 25, 50, 100])
            ->striped();
    }

    protected function buildReportQuery(): Builder
    {
        return app(FinanceMovementReportService::class)->salaryTransactionsQuery($this->salaryProfileId);
    }

    protected function validateReportFilters(): bool
    {
        if (! filled($this->salaryProfileId)) {
            Notification::make()
                ->title('اختر الموظف أولاً')
                ->warning()
                ->send();

            return false;
        }

        return true;
    }

    protected function exportSortColumn(): string
    {
        return 'transaction_date';
    }

    protected function downloadPdf(): mixed
    {
        $rows = $this->exportRows('لا توجد بيانات للطباعة');

        if ($rows === null) {
            return null;
        }

        $profile = app(FinanceMovementReportService::class)->resolveSalaryProfile($this->salaryProfileId);

        return PdfDownload::streamed(
            app(FinanceMovementPdfService::class)->salaryMovement(
                $rows,
                $profile?->name ?? '',
                (float) ($profile?->balance ?? 0),
            ),
        );
    }

    protected function downloadExcel(): mixed
    {
        $rows = $this->exportRows();

        if ($rows === null) {
            return null;
        }

        $profile = app(FinanceMovementReportService::class)->resolveSalaryProfile($this->salaryProfileId);

        return app(FinanceExcelService::class)->movementReport(
            rows: $rows,
            reportTitle: 'كشف حساب مرتب الموظف : '.($profile?->name ?? ''),
            kind: 'transaction',
            balance: (float) ($profile?->balance ?? 0),
            filename: 'salary-movement.xlsx',
        );
    }
}
