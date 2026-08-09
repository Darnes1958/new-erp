<?php

namespace App\Filament\Market\Pages\Reports;

use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Filament\Market\Pages\Reports\Concerns\RendersInvoiceReportContent;
use App\Filament\Market\Resources\SalesInvoices\SalesInvoiceResource;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\Warehouse;
use App\Services\Excel\MarketExcelService;
use App\Services\Market\SalesInvoicesReportService;
use App\Services\Pdf\InvoiceListPdfService;
use App\Support\PdfDownload;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class SalesInvoicesReportPage extends Page implements HasActions, HasForms, HasTable
{
    use HasTabs;
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;
    use RendersInvoiceReportContent;

    protected static ?string $navigationLabel = 'تقرير فواتير مبيعات';

    protected static ?string $title = 'تقرير فواتير مبيعات';

    protected static ?string $slug = 'sales-invoices-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::SalesInvoices;

    protected static ?int $navigationSort = 3;

    protected ?string $heading = '';

    #[Url(as: 'tab')]
    public ?string $activeTab = null;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('ادخال مبيعات') || $user?->can('تقارير مبيعات');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $this->loadDefaultActiveTab();
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
                        Actions::make([
                            InstallmentListPrintActions::compactPrint('print', fn () => $this->downloadPdf()),
                            InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadExcel()),
                        ])
                            ->columnSpan(2)
                            ->extraAttributes(['class' => 'market-compact-exports']),
                    ])
                    ->columns(6),
            ]);
    }

    public function getTabs(): array
    {
        $installmentId = SalesInvoicesReportService::installmentPaymentMethodId();

        return [
            'all' => Tab::make('الكل'),
            'installment' => Tab::make('تقسيط')
                ->modifyQueryUsing(fn (Builder $query): Builder => $installmentId
                    ? $query->where('payment_method_id', $installmentId)
                    : $query),
            'installment_active' => Tab::make('تقسيط قائم')
                ->modifyQueryUsing(fn (Builder $query): Builder => $installmentId
                    ? $query->where('payment_method_id', $installmentId)->whereHas('installmentContract')
                    : $query),
            'installment_archive' => Tab::make('تقسيط أرشيف')
                ->modifyQueryUsing(fn (Builder $query): Builder => $installmentId
                    ? $query->where('payment_method_id', $installmentId)->whereHas('installmentContractArchive')
                    : $query),
            'installment_no_contract' => Tab::make('تقسيط بدون عقد')
                ->modifyQueryUsing(fn (Builder $query): Builder => $installmentId
                    ? $query->where('payment_method_id', $installmentId)
                        ->whereDoesntHave('installmentContract')
                        ->whereDoesntHave('installmentContractArchive')
                    : $query),
            'cash' => Tab::make('نقداً')
                ->modifyQueryUsing(fn (Builder $query): Builder => $installmentId
                    ? $query->where('payment_method_id', '!=', $installmentId)
                    : $query),
            'cash_unpaid' => Tab::make('نقداً آجلة')
                ->modifyQueryUsing(fn (Builder $query): Builder => $installmentId
                    ? $query->where('payment_method_id', '!=', $installmentId)->where('balance', '!=', 0)
                    : $query->where('balance', '!=', 0)),
            'cash_paid' => Tab::make('نقداً مدفوعة')
                ->modifyQueryUsing(fn (Builder $query): Builder => $installmentId
                    ? $query->where('payment_method_id', '!=', $installmentId)->where('balance', 0)
                    : $query->where('balance', 0)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => SalesInvoice::query()
                ->with(['customer', 'paymentMethod', 'warehouse'])
                ->withSum('lines as profit_total', 'profit')
                ->withSum('salesReturns as returns_total', 'line_total'))
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...))
            ->defaultSort('id', 'desc')
            ->striped()
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم الالي')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('اسم الزبون')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('invoice_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('lines_subtotal')
                    ->label('اجمالي الفاتورة')
                    ->numeric(3)
                    ->sortable()
                    ->summarize(Sum::make()->label('')->numeric(3)),
                TextColumn::make('extra_cost')
                    ->label('تكاليف إضافية')
                    ->numeric(3)
                    ->summarize(Sum::make()->label('')->numeric(3)),
                TextColumn::make('paymentMethod.name')
                    ->label('طريقة الدفع')
                    ->color(fn (SalesInvoice $record): string => match ($record->paymentMethod?->code) {
                        'cash' => 'success',
                        'bank' => 'primary',
                        'installment' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('difference_amount')
                    ->label('فرق عملة')
                    ->numeric(3)
                    ->summarize(Sum::make()->label('')->numeric(3)),
                TextColumn::make('discount')
                    ->label('الخصم')
                    ->numeric(3)
                    ->summarize(Sum::make()->label('')->numeric(3)),
                TextColumn::make('grand_total')
                    ->label('الإجمالي النهائي')
                    ->numeric(3)
                    ->sortable()
                    ->summarize(Sum::make()->label('')->numeric(3)),
                TextColumn::make('amount_paid')
                    ->label('المدفوع')
                    ->numeric(3)
                    ->summarize(Sum::make()->label('')->numeric(3)),
                TextColumn::make('balance')
                    ->label('الباقي')
                    ->numeric(3)
                    ->color(fn (SalesInvoice $record): ?string => ($record->returns_total ?? 0) > 0 ? 'info' : null)
                    ->description(fn (SalesInvoice $record): ?string => ($record->returns_total ?? 0) > 0
                        ? number_format((float) $record->returns_total, 3, '.', ',').' ترجيع'
                        : null)
                    ->summarize(Sum::make()->label('')->numeric(3)),
                TextColumn::make('profit_total')
                    ->label('الربح')
                    ->numeric(3)
                    ->visible(fn (): bool => $this->canViewProfit())
                    ->summarize(Sum::make()->label('')->numeric(3)),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(40),
            ])
            ->filters([
                SelectFilter::make('customer_id')
                    ->label('زبون معين')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('warehouse_id')
                    ->label('نقطة بيع معينة')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('payment_method_id')
                    ->label('طريقة الدفع')
                    ->relationship('paymentMethod', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('invoice_date')
                    ->label('التاريخ')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('من تاريخ'),
                        DatePicker::make('date_to')
                            ->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('invoice_date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('invoice_date', '<=', $date),
                            );
                    })
                    ->default([
                        'date_from' => now()->startOfYear()->toDateString(),
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('عرض')
                    ->iconButton()
                    ->url(fn (SalesInvoice $record): string => SalesInvoiceResource::getUrl('view', ['record' => $record])),
                Action::make('print')
                    ->icon('heroicon-o-printer')
                    ->iconButton()
                    ->url(fn (SalesInvoice $record): string => route('pdf.sales-invoice', ['salesInvoice' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('لا توجد فواتير')
            ->paginated([10, 25, 50, 100]);
    }

    protected function downloadPdf(): mixed
    {
        $rows = $this->exportRows();

        if ($rows === null) {
            return null;
        }

        $meta = $this->exportMeta();

        return PdfDownload::streamed(
            app(InvoiceListPdfService::class)->salesInvoices($rows, $meta),
        );
    }

    protected function downloadExcel(): mixed
    {
        $rows = $this->exportRows();

        if ($rows === null) {
            return null;
        }

        return app(MarketExcelService::class)->salesInvoices($rows, $this->exportMeta());
    }

    /**
     * @return \Illuminate\Support\Collection<int, SalesInvoice>|null
     */
    protected function exportRows(): ?\Illuminate\Support\Collection
    {
        $rows = $this->getTableQueryForExport()->get();

        if ($rows->isEmpty()) {
            Notification::make()
                ->title('لا توجد بيانات للتصدير')
                ->warning()
                ->send();

            return null;
        }

        return $rows;
    }

    /**
     * @return array{
     *     dateFrom: ?string,
     *     dateTo: ?string,
     *     customerName: ?string,
     *     warehouseName: ?string,
     *     tabLabel: ?string,
     *     showProfit: bool
     * }
     */
    protected function exportMeta(): array
    {
        $dateFilter = $this->getTableFilterState('invoice_date') ?? [];
        $customerId = data_get($this->getTableFilterState('customer_id'), 'value');
        $warehouseId = data_get($this->getTableFilterState('warehouse_id'), 'value');
        $tabLabels = SalesInvoicesReportService::tabLabels();

        return [
            'dateFrom' => $dateFilter['date_from'] ?? null,
            'dateTo' => $dateFilter['date_to'] ?? null,
            'customerName' => filled($customerId)
                ? Customer::query()->whereKey($customerId)->value('name')
                : null,
            'warehouseName' => filled($warehouseId)
                ? Warehouse::query()->whereKey($warehouseId)->value('name')
                : null,
            'tabLabel' => $tabLabels[$this->activeTab ?? 'all'] ?? 'الكل',
            'showProfit' => $this->canViewProfit(),
        ];
    }

    protected function canViewProfit(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->is_prog || $user?->hasRole('admin'));
    }
}
