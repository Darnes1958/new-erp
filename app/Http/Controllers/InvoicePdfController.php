<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\SalesOfferInvoice;
use App\Services\Pdf\InvoicePdfService;
use App\Services\Pdf\PurchaseInvoiceItemPricesPdfService;
use Illuminate\Http\Response;

class InvoicePdfController extends Controller
{
    public function __construct(
        protected InvoicePdfService $pdfService,
        protected PurchaseInvoiceItemPricesPdfService $purchaseItemPricesPdfService,
    ) {}

    public function purchase(int $purchaseInvoice): Response
    {
        $invoice = PurchaseInvoice::query()->findOrFail($purchaseInvoice);

        return $this->pdfService->purchaseInvoice($invoice)->toResponse(request());
    }

    public function purchaseItemPrices(int $purchaseInvoice): Response
    {
        $invoice = PurchaseInvoice::query()->findOrFail($purchaseInvoice);

        return $this->purchaseItemPricesPdfService
            ->forInvoice($invoice)
            ->toResponse(request());
    }

    public function sales(int $salesInvoice): Response
    {
        $invoice = SalesInvoice::query()->findOrFail($salesInvoice);

        return $this->pdfService->salesInvoice($invoice)->toResponse(request());
    }

    public function salesOffer(int $salesOfferInvoice): Response
    {
        $invoice = SalesOfferInvoice::query()->findOrFail($salesOfferInvoice);

        return $this->pdfService->salesOfferInvoice($invoice)->toResponse(request());
    }
}
