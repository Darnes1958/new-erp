<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Services\Pdf\InvoicePdfService;
use Illuminate\Http\Response;

class InvoicePdfController extends Controller
{
    public function __construct(
        protected InvoicePdfService $pdfService,
    ) {}

    public function purchase(int $purchaseInvoice): Response
    {
        $invoice = PurchaseInvoice::query()->findOrFail($purchaseInvoice);

        return $this->pdfService->purchaseInvoice($invoice)->toResponse(request());
    }

    public function sales(int $salesInvoice): Response
    {
        $invoice = SalesInvoice::query()->findOrFail($salesInvoice);

        return $this->pdfService->salesInvoice($invoice)->toResponse(request());
    }
}
