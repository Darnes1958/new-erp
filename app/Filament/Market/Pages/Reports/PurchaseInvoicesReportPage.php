<?php

namespace App\Filament\Market\Pages\Reports;

use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Filament\Market\Pages\Reports\Concerns\RendersInvoiceReportContent;
use App\Filament\Market\Resources\PurchaseInvoices\Pages\EditBuy;
use App\Filament\Market\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Excel\MarketExcelService;
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
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseInvoicesReportPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;
    use RendersInvoiceReportContent;

    protected static ?string $navigationLabel = 'تقرير فواتير مشتريات';

    protected static ?string $title = 'تقرير فواتير مشتريات';

    protected static ?string $slug = 'purchase-invoices-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::PurchaseInvoices;

    protected static ?int $navigationSort = 4;

    protected ?string $heading = '';

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('ادخال مشتريات') || $user?->can('تقارير مشتريات');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
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

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PurchaseInvoice::query()->with(['supplier', 'warehouse']))
            ->defaultSort('id', 'desc')
            ->striped()
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم الالي')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('supplier.name')
                    ->label('اسم المورد')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('invoice_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('invoice_net_total')
                    ->label('اجمالي الفاتورة')
                    ->getStateUsing(fn (PurchaseInvoice $record): float => (float) $record->lines_subtotal - (float) $record->discount)
                    ->numeric(3)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw("(lines_subtotal - discount) {$direction}"))
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (QueryBuilder $query): float => (float) ($query->sum(DB::raw('(lines_subtotal - discount)')) ?? 0))
                            ->numeric(3),
                    ),
                TextColumn::make('amount_paid')
                    ->label('المدفوع')
                    ->numeric(3)
                    ->summarize(Sum::make()->label('')->numeric(3)),
                TextColumn::make('balance')
                    ->label('الباقي')
                    ->numeric(3)
                    ->summarize(Sum::make()->label('')->numeric(3)),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(40),
            ])
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('مورد معين')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('warehouse_id')
                    ->label('المخزن')
                    ->relationship('warehouse', 'name')
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
                    ->url(fn (PurchaseInvoice $record): string => PurchaseInvoiceResource::getUrl('view', ['record' => $record])),
                Action::make('print')
                    ->icon('heroicon-o-printer')
                    ->iconButton()
                    ->url(fn (PurchaseInvoice $record): string => route('pdf.purchase-invoice', ['purchaseInvoice' => $record->id]))
                    ->openUrlInNewTab(),
                Action::make('print_item_prices')
                    ->tooltip('طباعة اسعار الأصناف')
                    ->icon('heroicon-s-printer')
                    ->iconButton()
                    ->iconSize(IconSize::Small)
                    ->color('primary')
                    ->url(fn (PurchaseInvoice $record): string => route('pdf.purchase-invoice-item-prices', ['purchaseInvoice' => $record->id]))
                    ->openUrlInNewTab(),
                Action::make('edit_buy')
                    ->label('تعديل')
                    ->icon('heroicon-m-pencil')
                    ->iconButton()
                    ->color('info')
                    ->url(fn (Model $record): string => EditBuy::getUrl(['record' => $record])),
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

        return PdfDownload::streamed(
            app(InvoiceListPdfService::class)->purchaseInvoices($rows, $this->exportMeta()),
        );
    }

    protected function downloadExcel(): mixed
    {
        $rows = $this->exportRows();

        if ($rows === null) {
            return null;
        }

        return app(MarketExcelService::class)->purchaseInvoices($rows, $this->exportMeta());
    }

    /**
     * @return \Illuminate\Support\Collection<int, PurchaseInvoice>|null
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
     *     supplierName: ?string,
     *     warehouseName: ?string
     * }
     */
    protected function exportMeta(): array
    {
        $dateFilter = $this->getTableFilterState('invoice_date') ?? [];
        $supplierId = data_get($this->getTableFilterState('supplier_id'), 'value');
        $warehouseId = data_get($this->getTableFilterState('warehouse_id'), 'value');

        return [
            'dateFrom' => $dateFilter['date_from'] ?? null,
            'dateTo' => $dateFilter['date_to'] ?? null,
            'supplierName' => filled($supplierId)
                ? Supplier::query()->whereKey($supplierId)->value('name')
                : null,
            'warehouseName' => filled($warehouseId)
                ? Warehouse::query()->whereKey($warehouseId)->value('name')
                : null,
        ];
    }
}
