<?php

namespace App\Services\Pdf;

use App\Models\OurCompany;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
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
        $invoice->load(['customer', 'warehouse', 'lines.item']);

        return Pdf::view('pdf.sales-invoice', [
            'invoice' => $invoice,
            'company' => OurCompany::forCurrentUser(),
            'hasDualUnit' => CompanySettings::hasDualUnit(),
            'repDate' => now()->toDateString(),
        ])
            ->name("sales-invoice-{$invoice->id}.pdf")
            ->download();
    }
}
