<?php

namespace App\Filament\Ins\Pages\Reports;

use App\Services\Excel\InstallmentExcelService;
use App\Services\Installments\InstallmentBankTotalsReportService;
use App\Services\Pdf\InstallmentBankTotalsPdfService;
use App\Support\PdfDownload;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Radio;
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

class BankTotalsReportPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'إجمالي المصارف';

    protected static ?string $title = 'إجمالي المصارف';

    protected static ?string $slug = 'bank-totals-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'تقارير';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.ins.pages.reports.bank-totals-report';

    protected ?string $heading = '';

    public int $filterBy = 1;

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
        $this->filtersForm->fill([
            'filterBy' => $this->filterBy,
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
                Radio::make('filterBy')
                    ->hiddenLabel()
                    ->inline()
                    ->live()
                    ->options([
                        1 => 'بفروع المصارف',
                        2 => 'بالتجميعي',
                    ]),
            ])
            ->columns(1)
            ->extraAttributes(['class' => 'gap-y-2']);
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

    public function updatedFilterBy(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $service = app(InstallmentBankTotalsReportService::class);

        return $table
            ->query(fn (): Builder => $service->reportQuery($this->filterBy))
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label(fn (): string => $this->filterBy === 2 ? 'المصرف التجميعي' : 'الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contracts_count')
                    ->label('عدد العقود')
                    ->numeric(0)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): int => $service->summary($this->filterBy)['contracts_count']),
                    ),
                TextColumn::make('contracts_total')
                    ->label('اجمالي العقود')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format($service->summary($this->filterBy)['contracts_total'], 3, '.', ',')),
                    ),
                TextColumn::make('total_paid')
                    ->label('المسدد')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format($service->summary($this->filterBy)['total_paid'], 3, '.', ',')),
                    ),
                TextColumn::make('balance_total')
                    ->label('الرصيد')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format($service->summary($this->filterBy)['balance_total'], 3, '.', ',')),
                    ),
                TextColumn::make('surplus_total')
                    ->label('الفائض')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format($service->summary($this->filterBy)['surplus_total'], 3, '.', ',')),
                    ),
                TextColumn::make('suspended_total')
                    ->label('الترجيع')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format($service->summary($this->filterBy)['suspended_total'], 3, '.', ',')),
                    ),
                TextColumn::make('wrong_total')
                    ->label('بالخطأ')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format($service->summary($this->filterBy)['wrong_total'], 3, '.', ',')),
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
            app(InstallmentBankTotalsPdfService::class)->report(
                $context['rows'],
                $this->filterBy,
                $context['summary'],
                $context['reportTitle'],
            ),
        );
    }

    protected function downloadExcel(): mixed
    {
        $context = $this->resolveExportContext();

        if ($context === null) {
            return null;
        }

        return app(InstallmentExcelService::class)->bankTotalsReport(
            $context['rows'],
            $this->filterBy,
            $context['reportTitle'],
        );
    }

    /**
     * @return array{
     *     rows: Collection,
     *     summary: array<string, float|int>,
     *     reportTitle: string
     * }|null
     */
    protected function resolveExportContext(string $emptyMessage = 'لا توجد بيانات للتصدير'): ?array
    {
        $service = app(InstallmentBankTotalsReportService::class);
        $rows = $service->exportRows($this->filterBy);

        if ($rows->isEmpty()) {
            Notification::make()
                ->title($emptyMessage)
                ->warning()
                ->send();

            return null;
        }

        return [
            'rows' => $rows,
            'summary' => $service->summary($this->filterBy),
            'reportTitle' => $service->reportTitle($this->filterBy),
        ];
    }
}
