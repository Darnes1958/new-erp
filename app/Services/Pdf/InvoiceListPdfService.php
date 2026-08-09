<?php

namespace App\Services\Pdf;

use App\Models\OurCompany;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Support\ErpNumber;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class InvoiceListPdfService
{
    /**
     * @param  Collection<int, SalesInvoice>  $rows
     * @param  array{
     *     dateFrom: ?string,
     *     dateTo: ?string,
     *     customerName: ?string,
     *     warehouseName: ?string,
     *     tabLabel: ?string,
     *     showProfit: bool
     * }  $meta
     */
    public function salesInvoices(Collection $rows, array $meta, ?OurCompany $company = null): PdfBuilder
    {
        $headers = ['اسم الزبون', 'رقم الفاتورة', 'التاريخ', 'إجمالي البنود', 'تكاليف إضافية', 'خصم', 'فرق عملة', 'الإجمالي', 'المدفوع', 'الباقي'];

        if ($meta['showProfit']) {
            $headers[] = 'الربح';
        }

        $mappedRows = $rows->map(function (SalesInvoice $invoice) use ($meta): array {
            $row = [
                $invoice->customer?->name ?? '—',
                (string) $invoice->id,
                $invoice->invoice_date?->format('Y-m-d') ?? '—',
                ErpNumber::money($invoice->lines_subtotal),
                ErpNumber::money($invoice->extra_cost),
                ErpNumber::money($invoice->discount),
                ErpNumber::money($invoice->difference_amount),
                ErpNumber::money($invoice->grand_total),
                ErpNumber::money($invoice->amount_paid),
                ErpNumber::money($invoice->balance),
            ];

            if ($meta['showProfit']) {
                $row[] = ErpNumber::money($invoice->profit_total ?? 0);
            }

            return $row;
        });

        $totals = [
            'الإجمالي',
            '',
            '',
            ErpNumber::money($rows->sum('lines_subtotal')),
            ErpNumber::money($rows->sum('extra_cost')),
            ErpNumber::money($rows->sum('discount')),
            ErpNumber::money($rows->sum('difference_amount')),
            ErpNumber::money($rows->sum('grand_total')),
            ErpNumber::money($rows->sum('amount_paid')),
            ErpNumber::money($rows->sum('balance')),
        ];

        if ($meta['showProfit']) {
            $totals[] = ErpNumber::money($rows->sum('profit_total'));
        }

        return $this->buildPdf(
            company: $company,
            reportTitle: 'تقرير فواتير المبيعات',
            partyLabel: 'الزبون',
            partyName: $meta['customerName'],
            dateFrom: $meta['dateFrom'],
            dateTo: $meta['dateTo'],
            warehouseName: $meta['warehouseName'],
            tabLabel: $meta['tabLabel'],
            headers: $headers,
            rows: $mappedRows,
            totals: $totals,
            filename: 'sales-invoices-report.pdf',
        );
    }

    /**
     * @param  Collection<int, PurchaseInvoice>  $rows
     * @param  array{
     *     dateFrom: ?string,
     *     dateTo: ?string,
     *     supplierName: ?string,
     *     warehouseName: ?string
     * }  $meta
     */
    public function purchaseInvoices(Collection $rows, array $meta, ?OurCompany $company = null): PdfBuilder
    {
        $headers = ['اسم المورد', 'رقم الفاتورة', 'التاريخ', 'الإجمالي', 'المدفوع', 'الباقي', 'ملاحظات'];

        $mappedRows = $rows->map(function (PurchaseInvoice $invoice): array {
            $netTotal = (float) $invoice->lines_subtotal - (float) $invoice->discount;

            return [
                $invoice->supplier?->name ?? '—',
                (string) $invoice->id,
                $invoice->invoice_date?->format('Y-m-d') ?? '—',
                ErpNumber::money($netTotal),
                ErpNumber::money($invoice->amount_paid),
                ErpNumber::money($invoice->balance),
                $invoice->notes ?? '',
            ];
        });

        $totals = [
            'الإجمالي',
            '',
            '',
            ErpNumber::money($rows->sum(fn (PurchaseInvoice $invoice): float => (float) $invoice->lines_subtotal - (float) $invoice->discount)),
            ErpNumber::money($rows->sum('amount_paid')),
            ErpNumber::money($rows->sum('balance')),
            '',
        ];

        return $this->buildPdf(
            company: $company,
            reportTitle: 'تقرير فواتير المشتريات',
            partyLabel: 'المورد',
            partyName: $meta['supplierName'],
            dateFrom: $meta['dateFrom'],
            dateTo: $meta['dateTo'],
            warehouseName: $meta['warehouseName'],
            tabLabel: null,
            headers: $headers,
            rows: $mappedRows,
            totals: $totals,
            filename: 'purchase-invoices-report.pdf',
        );
    }

    /**
     * @param  Collection<int, array<int, string>>  $rows
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $totals
     */
    protected function buildPdf(
        ?OurCompany $company,
        string $reportTitle,
        string $partyLabel,
        ?string $partyName,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $warehouseName,
        ?string $tabLabel,
        array $headers,
        Collection $rows,
        array $totals,
        string $filename,
    ): PdfBuilder {
        return Pdf::view('pdf.market.invoice-list', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'reportTitle' => $reportTitle,
            'partyLabel' => $partyLabel,
            'partyName' => $partyName,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'warehouseName' => $warehouseName,
            'tabLabel' => $tabLabel,
            'headers' => $headers,
            'rows' => $rows,
            'totals' => $totals,
        ])->name($filename);
    }
}
