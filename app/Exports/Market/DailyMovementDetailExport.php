<?php

namespace App\Exports\Market;

use App\Exports\Market\Concerns\BuildsDailyMovementExcelSheet;
use App\Enums\RentTransactionType;
use App\Enums\SalaryTransactionType;
use App\Models\OurCompany;
use App\Services\Market\DailyMovementReportService;
use App\Support\Utf8Text;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class DailyMovementDetailExport implements FromCollection, WithEvents, WithHeadings
{
    use BuildsDailyMovementExcelSheet;

    protected DailyMovementReportService $service;

    /**
     * @param  Collection<int, \App\Models\PurchaseInvoice>  $purchases
     * @param  Collection<int, \App\Models\SalesInvoice>  $sales
     * @param  Collection<int, \App\Models\SupplierPayment>  $supplierPayments
     * @param  Collection<int, \App\Models\CustomerReceipt>  $customerReceipts
     * @param  Collection<int, \App\Models\SalesReturn>  $salesReturns
     * @param  Collection<int, \App\Models\PurchaseReturn>  $purchaseReturns
     * @param  Collection<int, \App\Models\Expense>  $expenses
     * @param  Collection<int, \App\Models\SalaryTransaction>  $salaries
     * @param  Collection<int, \App\Models\RentTransaction>  $rents
     */
    public function __construct(
        protected ?OurCompany $company,
        protected ?string $dateFrom,
        protected ?string $dateTo,
        protected ?string $warehouseName,
        protected Collection $purchases,
        protected Collection $sales,
        protected Collection $supplierPayments,
        protected Collection $customerReceipts,
        protected Collection $salesReturns,
        protected Collection $purchaseReturns,
        protected Collection $expenses,
        protected Collection $salaries,
        protected Collection $rents,
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
                    'الحركة اليومية — تفصيلي',
                    $this->dateFrom,
                    $this->dateTo,
                    $this->warehouseName,
                    7,
                );

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'فواتير المشتريات',
                    ['رقم الفاتورة', 'التاريخ', 'المورد', 'الإجمالي', 'المدفوع', 'المتبقي', 'ملاحظات'],
                    $this->purchases->map(fn ($invoice): array => [
                        $invoice->id,
                        $invoice->invoice_date?->format('Y-m-d'),
                        Utf8Text::clean($invoice->supplier?->name),
                        (float) ($invoice->invoice_total ?? ($invoice->lines_subtotal - $invoice->discount)),
                        (float) $invoice->amount_paid,
                        (float) $invoice->balance,
                        Utf8Text::clean($invoice->notes),
                    ])->all(),
                    ['A' => 12, 'B' => 14, 'C' => 28, 'D' => 14, 'E' => 14, 'F' => 14, 'G' => 30],
                    [4, 5, 6],
                );

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'فواتير المبيعات',
                    ['رقم الفاتورة', 'التاريخ', 'الزبون', 'الإجمالي', 'المدفوع', 'المتبقي', 'ملاحظات'],
                    $this->sales->map(fn ($invoice): array => [
                        $invoice->id,
                        $invoice->invoice_date?->format('Y-m-d'),
                        Utf8Text::clean($invoice->customer?->name),
                        (float) $invoice->grand_total,
                        (float) $invoice->amount_paid,
                        (float) $invoice->balance,
                        Utf8Text::clean($invoice->notes),
                    ])->all(),
                    ['A' => 12, 'B' => 14, 'C' => 28, 'D' => 14, 'E' => 14, 'F' => 14, 'G' => 30],
                    [4, 5, 6],
                );

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'إيصالات الموردين',
                    ['الرقم', 'التاريخ', 'المورد', 'البيان', 'النوع', 'المبلغ'],
                    $this->supplierPayments->map(fn ($payment): array => [
                        $payment->id,
                        $payment->payment_date?->format('Y-m-d'),
                        Utf8Text::clean($payment->supplier?->name),
                        Utf8Text::clean($this->service->transactionKindLabel((int) $payment->transaction_kind)),
                        (int) $payment->flow_direction === 0 ? 'قبض' : 'دفع',
                        (float) $payment->amount,
                    ])->all(),
                    ['A' => 10, 'B' => 14, 'C' => 24, 'D' => 18, 'E' => 10, 'F' => 14],
                    [6],
                );

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'إيصالات الزبائن',
                    ['الرقم', 'التاريخ', 'الزبون', 'البيان', 'النوع', 'المبلغ'],
                    $this->customerReceipts->map(fn ($receipt): array => [
                        $receipt->id,
                        $receipt->receipt_date?->format('Y-m-d'),
                        Utf8Text::clean($receipt->customer?->name),
                        Utf8Text::clean($this->service->transactionKindLabel((int) $receipt->transaction_kind)),
                        (int) $receipt->flow_direction === 0 ? 'قبض' : 'دفع',
                        (float) $receipt->amount,
                    ])->all(),
                    ['A' => 10, 'B' => 14, 'C' => 24, 'D' => 18, 'E' => 10, 'F' => 14],
                    [6],
                );

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'ترجيع مبيعات',
                    ['التاريخ', 'الزبون', 'الصنف', 'الكمية', 'الإجمالي'],
                    $this->salesReturns->map(fn ($return): array => [
                        $return->return_date?->format('Y-m-d'),
                        Utf8Text::clean($return->salesInvoice?->customer?->name),
                        Utf8Text::clean($return->item?->name),
                        (float) $return->qty_primary,
                        (float) $return->line_total,
                    ])->all(),
                    ['A' => 14, 'B' => 24, 'C' => 24, 'D' => 12, 'E' => 14],
                    [4, 5],
                );

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'ترجيع مشتريات',
                    ['التاريخ', 'المورد', 'الصنف', 'الكمية', 'الإجمالي'],
                    $this->purchaseReturns->map(fn ($return): array => [
                        $return->return_date?->format('Y-m-d'),
                        Utf8Text::clean($return->purchaseInvoice?->supplier?->name),
                        Utf8Text::clean($return->item?->name),
                        (float) $return->qty_primary,
                        (float) $return->line_total,
                    ])->all(),
                    ['A' => 14, 'B' => 24, 'C' => 24, 'D' => 12, 'E' => 14],
                    [4, 5],
                );

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'المصروفات',
                    ['التاريخ', 'البيان', 'دفعت من', 'المبلغ'],
                    $this->expenses->map(fn ($expense): array => [
                        $expense->expense_date?->format('Y-m-d'),
                        Utf8Text::clean($expense->expenseType?->name),
                        Utf8Text::clean($this->service->paymentSourceLabel($expense->bankAccount?->name, $expense->cashBox?->name)),
                        (float) $expense->amount,
                    ])->all(),
                    ['A' => 14, 'B' => 24, 'C' => 24, 'D' => 14],
                    [4],
                );

                $row = $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'المرتبات',
                    ['التاريخ', 'الموظف', 'البيان', 'دفعت من', 'عن شهر', 'المبلغ', 'ملاحظات'],
                    $this->salaries->map(fn ($salary): array => [
                        $salary->transaction_date?->format('Y-m-d'),
                        Utf8Text::clean($salary->salaryProfile?->name),
                        Utf8Text::clean($salary->transaction_type instanceof SalaryTransactionType
                            ? $salary->transaction_type->getLabel()
                            : (string) $salary->transaction_type),
                        Utf8Text::clean($this->service->paymentSourceLabel($salary->bankAccount?->name, $salary->cashBox?->name)),
                        filled($salary->period_month) && $salary->period_month !== '0' ? $salary->period_month : '—',
                        (float) $salary->amount,
                        Utf8Text::clean($salary->notes),
                    ])->all(),
                    ['A' => 14, 'B' => 24, 'C' => 14, 'D' => 24, 'E' => 14, 'F' => 14, 'G' => 30],
                    [6],
                );

                $this->writeDailyMovementSection(
                    $sheet,
                    $row,
                    'الإيجارات',
                    ['التاريخ', 'الإيجار', 'البيان', 'دفعت من', 'عن شهر', 'المبلغ', 'ملاحظات'],
                    $this->rents->map(fn ($rent): array => [
                        $rent->transaction_date?->format('Y-m-d'),
                        Utf8Text::clean($rent->rentProfile?->name),
                        Utf8Text::clean($rent->transaction_type instanceof RentTransactionType
                            ? $rent->transaction_type->getLabel()
                            : (string) $rent->transaction_type),
                        Utf8Text::clean($this->service->paymentSourceLabel($rent->bankAccount?->name, $rent->cashBox?->name)),
                        filled($rent->period_month) && $rent->period_month !== '0' ? $rent->period_month : '—',
                        (float) $rent->amount,
                        Utf8Text::clean($rent->notes),
                    ])->all(),
                    ['A' => 14, 'B' => 24, 'C' => 14, 'D' => 24, 'E' => 14, 'F' => 14, 'G' => 30],
                    [6],
                );
            },
        ];
    }
}
