<?php

namespace App\Http\Controllers;

use App\Models\WarehouseTransfer;
use App\Services\Pdf\WarehouseTransferPdfService;
use Illuminate\Http\Response;

class WarehouseTransferPdfController extends Controller
{
    public function __construct(
        protected WarehouseTransferPdfService $pdfService,
    ) {}

    public function __invoke(int $warehouseTransfer): Response
    {
        $transfer = WarehouseTransfer::query()->findOrFail($warehouseTransfer);

        return $this->pdfService->transfer($transfer)->toResponse(request());
    }
}
