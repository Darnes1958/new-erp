<?php

namespace App\Exports\Market;

use App\Exports\Market\Concerns\BuildsDailyMovementExcelSheet;
use App\Models\OurCompany;
use App\Services\Market\DailyMovementReportService;
use App\Support\ErpNumber;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class DailyMovementSummaryExport implements FromCollection, WithEvents, WithHeadings
{
    use BuildsDailyMovementExcelSheet;

    protected DailyMovementReportService $service;

    /**
     * @param  array{
     *     purchases: float,
     *     sales: float,
     *     collections: float,
     *     payments: float,
     *     purchase_returns: float,
     *     sales_returns: float,
     *     expenses: float,
     *     net_cash_flow: float
     * }  $stats
     * @param  Collection<int, object>  $purchases
     * @param  Collection<int, object>  $sales
     * @param  Collection<int, object>  $supplierPayments
     * @param  Collection<int, object>  $customerReceipts
     * @param  Collection<int, object>  $expenses
     * @param  Collection<int, object>  $salaries
     * @param  Collection<int, object>  $rents
     * @param  Collection<int, object>  $salesReturns
     * @param  Collection<int, object>  $purchaseReturns
     * @param  Collection<int, object>  $cashBoxes
     */
    public function __construct(
        protected ?OurCompany $company,
        protected ?string $dateFrom,
        protected ?string $dateTo,
        protected ?string $warehouseName,
        protected array $stats,
        protected Collection $purchases,
        protected Collection $sales,
        protected Collection $supplierPayments,
        protected Collection $customerReceipts,
        protected Collection $expenses,
        protected Collection $salaries,
        protected Collection $rents,
        protected Collection $salesReturns,
        protected Collection $purchaseReturns,
        protected Collection $cashBoxes,
    ) {
        $this->service = app(DailyMovementReportService::class);
    }

    public function collection(): Collection
    {
        return collect();
    }

    public function headings(): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $row = $this->initializeDailyMovementSheet(
                    $sheet,
                    $this->company,
                    'خلاصة الحركة اليومية',
                    $this->dateFrom,
                    $this->dateTo,
                    $this->warehouseName,
                    5,
                );

                $statsRows = [
                    ['مشتريات', ErpNumber::money($this->stats['purchases']), 'مبيعات', ErpNumber::money($this->stats['sales']), 'قبض', ErpNumber::money($this->stats['collections'])],
                    ['دفع', ErpNumber::money($this->stats['payments']), 'ترجيع مشتريات', ErpNumber::money($this->stats['purchase_returns']), 'ترجيع مبيعات', ErpNumber::money($this->stats['sales_returns'])],
                    ['مصروفات', ErpNumber::money($this->stats['expenses']), 'صافي التدفق', ErpNumber::money($this->stats['net_cash_flow']), '', ''],
                ];

                $sheet->mergeCells("A{$row}:F{$row}");
                $sheet->setCellValue("A{$row}", 'ملخص الأرقام');
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $row++;

                foreach ($statsRows as $statsRow) {
                    $sheet->fromArray($statsRow, null, "A{$row}");
                    $sheet->getStyle("A{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $row++;
                }

                $row++;

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'المشتريات',
                    ['نقطة البيع', 'الإجمالي', 'المدفوع', 'الباقي'],
                    $this->purchases->map(fn ($item): array => [
                        Utf8Text::clean($item->warehouse_name),
                        (float) $item->total_amount,
                        (float) $item->paid_amount,
                        (float) $item->balance_amount,
                    ])->all(),
                    ['A' => 24, 'B' => 14, 'C' => 14, 'D' => 14],
                    [2, 3, 4],
                );

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'المبيعات',
                    ['نقطة البيع', 'الإجمالي', 'المدفوع', 'الباقي'],
                    $this->sales->map(fn ($item): array => [
                        Utf8Text::clean($item->warehouse_name),
                        (float) $item->total_amount,
                        (float) $item->paid_amount,
                        (float) $item->balance_amount,
                    ])->all(),
                    ['A' => 24, 'B' => 14, 'C' => 14, 'D' => 14],
                    [2, 3, 4],
                );

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'إيصالات الموردين',
                    ['البيان', 'طريقة الدفع', 'الخزينة / الحساب', 'قبض', 'دفع'],
                    $this->supplierPayments->map(fn ($item): array => [
                        Utf8Text::clean($this->service->transactionKindLabel((int) $item->transaction_kind)),
                        Utf8Text::clean($item->payment_method_name),
                        Utf8Text::clean($this->service->paymentSourceLabel($item->bank_account_name ?? null, $item->cash_box_name ?? null)),
                        (float) $item->collection_amount,
                        (float) $item->payment_amount,
                    ])->all(),
                    ['A' => 18, 'B' => 18, 'C' => 24, 'D' => 14, 'E' => 14],
                    [4, 5],
                );

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'إيصالات الزبائن',
                    ['البيان', 'طريقة الدفع', 'الخزينة / الحساب', 'قبض', 'دفع'],
                    $this->customerReceipts->map(fn ($item): array => [
                        Utf8Text::clean($this->service->transactionKindLabel((int) $item->transaction_kind)),
                        Utf8Text::clean($item->payment_method_name),
                        Utf8Text::clean($this->service->paymentSourceLabel($item->bank_account_name ?? null, $item->cash_box_name ?? null)),
                        (float) $item->collection_amount,
                        (float) $item->payment_amount,
                    ])->all(),
                    ['A' => 18, 'B' => 18, 'C' => 24, 'D' => 14, 'E' => 14],
                    [4, 5],
                );

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'المصروفات',
                    ['نوع المصروف', 'دفعت من', 'المبلغ'],
                    $this->expenses->map(fn ($item): array => [
                        Utf8Text::clean($item->expense_type_name),
                        Utf8Text::clean($item->payment_source_name),
                        (float) $item->total_amount,
                    ])->all(),
                    ['A' => 24, 'B' => 24, 'C' => 14],
                    [3],
                );

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'المرتبات',
                    ['البيان', 'المبلغ'],
                    $this->salaries->map(fn ($item): array => [
                        Utf8Text::clean($item->transaction_type),
                        (float) $item->total_amount,
                    ])->all(),
                    ['A' => 24, 'B' => 14],
                    [2],
                );

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'الإيجارات',
                    ['البيان', 'المبلغ'],
                    $this->rents->map(fn ($item): array => [
                        Utf8Text::clean($item->transaction_type),
                        (float) $item->total_amount,
                    ])->all(),
                    ['A' => 24, 'B' => 14],
                    [2],
                );

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'ترجيع مبيعات',
                    ['التاريخ', 'الإجمالي'],
                    $this->salesReturns->map(fn ($item): array => [
                        optional($item->return_date)->format('Y-m-d'),
                        (float) $item->total_amount,
                    ])->all(),
                    ['A' => 14, 'B' => 14],
                    [2],
                );

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'ترجيع مشتريات',
                    ['التاريخ', 'الإجمالي'],
                    $this->purchaseReturns->map(fn ($item): array => [
                        optional($item->return_date)->format('Y-m-d'),
                        (float) $item->total_amount,
                    ])->all(),
                    ['A' => 14, 'B' => 14],
                    [2],
                );

                $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'أرصدة الخزائن',
                    ['الخزينة', 'وارد', 'صادر', 'الصافي'],
                    $this->cashBoxes->map(fn ($item): array => [
                        Utf8Text::clean($item->cash_box_name),
                        (float) $item->inflow_amount,
                        (float) $item->outflow_amount,
                        (float) $item->net_amount,
                    ])->all(),
                    ['A' => 24, 'B' => 14, 'C' => 14, 'D' => 14],
                    [2, 3, 4],
                );
            },
        ];
    }
}
