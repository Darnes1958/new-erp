<?php

namespace App\Services\Pdf;

use App\Models\OurCompany;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceWork;
use App\Models\SalesOfferInvoice;
use App\Support\CompanySettings;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class InvoicePdfService
{
    public function purchaseInvoice(PurchaseInvoice $invoice): PdfBuilder
    {
        $invoice->load(['supplier', 'warehouse', 'lines.item']);

        return Pdf::view('pdf.purchase-invoice', [
            'invoice' => $invoice,
            'company' => OurCompany::forCurrentUser(),
            'hasDualUnit' => CompanySettings::hasDualUnit(),
            'repDate' => now()->toDateString(),
        ])
            ->name("purchase-invoice-{$invoice->id}.pdf")
            ->download();
    }

    public function salesInvoice(SalesInvoice $invoice): PdfBuilder
    {
        return Pdf::view('pdf.sales-invoice', $this->salesDocumentData($invoice))
            ->name("sales-invoice-{$invoice->id}.pdf")
            ->download();
    }

    public function salesDispatchNote(SalesInvoice $invoice): PdfBuilder
    {
        return Pdf::view('pdf.sales-dispatch-note', $this->salesDocumentData($invoice))
            ->name("sales-dispatch-note-{$invoice->id}.pdf")
            ->download();
    }

    public function salesInvoicePrintSet(SalesInvoice $invoice): PdfBuilder
    {
        return Pdf::view('pdf.sales-invoice-print-set', $this->salesDocumentData($invoice))
            ->name("sales-invoice-print-set-{$invoice->id}.pdf")
            ->download();
    }

    public function salesWorkPrintSet(SalesInvoiceWork $work): PdfBuilder
    {
        $work->load(['customer', 'warehouse', 'lines.item']);

        return Pdf::view('pdf.sales-invoice-print-set', [
            ...$this->salesDocumentData($work),
            'invoiceTitle' => 'فاتورة مبيعات (مسودة)',
            'dispatchTitle' => 'إذن صرف بضاعة (مسودة)',
        ])
            ->name('sales-invoice-work-print-set.pdf')
            ->download();
    }

    public function salesOfferInvoice(SalesOfferInvoice $invoice): PdfBuilder
    {
        $invoice->load(['customer', 'warehouse', 'lines.item']);

        return Pdf::view('pdf.sales-offer-invoice', [
            'invoice' => $invoice,
            'company' => OurCompany::forCurrentUser(),
            'hasDualUnit' => CompanySettings::hasDualUnit(),
            'repDate' => now()->toDateString(),
        ])
            ->name("sales-offer-invoice-{$invoice->id}.pdf")
            ->download();
    }

    /**
     * @return array{invoice: SalesInvoice|SalesInvoiceWork, company: ?OurCompany, hasDualUnit: bool, repDate: string}
     */
    protected function salesDocumentData(SalesInvoice|SalesInvoiceWork $invoice): array
    {
        $invoice->loadMissing(['customer', 'warehouse', 'lines.item']);

        return [
            'invoice' => $invoice,
            'company' => OurCompany::forCurrentUser(),
            'hasDualUnit' => CompanySettings::hasDualUnit(),
            'repDate' => now()->toDateString(),
        ];
    }
}
