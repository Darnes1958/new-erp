<?php

namespace App\Services\Pdf;

use App\Models\OurCompany;
use App\Models\WarehouseTransfer;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class WarehouseTransferPdfService
{
    public function transfer(WarehouseTransfer $transfer): PdfBuilder
    {
        $transfer->load(['warehouseFrom', 'warehouseTo', 'lines.item']);

        return Pdf::view('pdf.warehouse-transfer', [
            'transfer' => $transfer,
            'company' => OurCompany::forCurrentUser(),
            'repDate' => now()->toDateString(),
        ])
            ->name("warehouse-transfer-{$transfer->id}.pdf")
            ->download();
    }
}
