<?php

namespace App\Filament\Ins\Pages\Reports;

use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Models\Warehouse;
use App\Services\Excel\InstallmentExcelService;
use App\Services\Installments\InstallmentBranchTotalsReportService;
use App\Services\Pdf\InstallmentBankTotalsPdfService;
use App\Support\PdfDownload;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
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

class BranchTotalsReportPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'إجمالي الفروع';

    protected static ?string $title = 'إجمالي الفروع';

    protected static ?string $slug = 'branch-totals-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|\UnitEnum|null $navigationGroup = 'تقارير';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.ins.pages.reports.branch-totals-report';

    protected ?string $heading = '';

    public ?int $warehouseId = null;

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
        $this->warehouseId = Warehouse::query()->orderBy('name')->value('id');

        $this->filtersForm->fill([
            'warehouseId' => $this->warehouseId,
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
                Select::make('warehouseId')
                    ->hiddenLabel()
                    ->prefix('الفرع')
                    ->options(fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
            ])
            ->columns(1)
            ->extraAttributes(['class' => 'gap-y-2']);
    }

    public function printAction(): Action
    {
        return InstallmentListPrintActions::compactPrint('print', fn () => $this->downloadPdf());
    }

    public function exportExcelAction(): Action
    {
        return InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadExcel());
    }

    public function updatedWarehouseId(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $service = app(InstallmentBranchTotalsReportService::class);

        return $table
            ->query(fn (): Builder => $service->reportQuery($this->warehouseId))
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('المصرف التجميعي')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contracts_count')
                    ->label('عدد العقود')
                    ->numeric(0)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): int => $service->summary($this->warehouseId)['contracts_count']),
                    ),
                TextColumn::make('contracts_total')
                    ->label('اجمالي العقود')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format(
                                $service->summary($this->warehouseId)['contracts_total'],
                                3,
                                '.',
                                ',',
                            )),
                    ),
                TextColumn::make('total_paid')
                    ->label('المسدد')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format(
                                $service->summary($this->warehouseId)['total_paid'],
                                3,
                                '.',
                                ',',
                            )),
                    ),
                TextColumn::make('balance_total')
                    ->label('الرصيد')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format(
                                $service->summary($this->warehouseId)['balance_total'],
                                3,
                                '.',
                                ',',
                            )),
                    ),
                TextColumn::make('surplus_total')
                    ->label('الفائض')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format(
                                $service->summary($this->warehouseId)['surplus_total'],
                                3,
                                '.',
                                ',',
                            )),
                    ),
                TextColumn::make('suspended_total')
                    ->label('الترجيع')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format(
                                $service->summary($this->warehouseId)['suspended_total'],
                                3,
                                '.',
                                ',',
                            )),
                    ),
                TextColumn::make('wrong_total')
                    ->label('بالخطأ')
                    ->numeric(3)
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (): string => number_format(
                                $service->summary($this->warehouseId)['wrong_total'],
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
            app(InstallmentBankTotalsPdfService::class)->report(
                $context['rows'],
                2,
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
            2,
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
        if (! $this->warehouseId) {
            Notification::make()
                ->title('اختر الفرع أولاً')
                ->warning()
                ->send();

            return null;
        }

        $service = app(InstallmentBranchTotalsReportService::class);
        $rows = $service->exportRows($this->warehouseId);

        if ($rows->isEmpty()) {
            Notification::make()
                ->title($emptyMessage)
                ->warning()
                ->send();

            return null;
        }

        return [
            'rows' => $rows,
            'summary' => $service->summary($this->warehouseId),
            'reportTitle' => $service->reportTitle($this->warehouseId),
        ];
    }
}
