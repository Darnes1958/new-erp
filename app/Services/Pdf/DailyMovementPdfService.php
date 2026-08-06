<?php

namespace App\Services\Pdf;

use App\Models\OurCompany;
use App\Services\Market\DailyMovementReportService;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class DailyMovementPdfService
{
    public function detail(
        DailyMovementReportService $service,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $warehouseId,
        ?string $warehouseName,
        ?OurCompany $company = null,
    ): PdfBuilder {
        return Pdf::view('pdf.market.daily-movement-detail', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'warehouseName' => $warehouseName,
            'purchases' => $service->purchasesDetailQuery($dateFrom, $dateTo, $warehouseId)->get(),
            'sales' => $service->salesDetailQuery($dateFrom, $dateTo, $warehouseId)->get(),
            'supplierPayments' => $service->supplierPaymentsDetailQuery($dateFrom, $dateTo, $warehouseId)->get(),
            'customerReceipts' => $service->customerReceiptsDetailQuery($dateFrom, $dateTo, $warehouseId)->get(),
            'salesReturns' => $service->salesReturnsDetailQuery($dateFrom, $dateTo, $warehouseId)->get(),
            'purchaseReturns' => $service->purchaseReturnsDetailQuery($dateFrom, $dateTo, $warehouseId)->get(),
            'expenses' => $service->expensesDetailQuery($dateFrom, $dateTo, $warehouseId)->get(),
            'service' => $service,
        ])->name('daily-movement-detail.pdf');
    }

    public function summary(
        DailyMovementReportService $service,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $warehouseId,
        ?string $warehouseName,
        ?OurCompany $company = null,
    ): PdfBuilder {
        return Pdf::view('pdf.market.daily-movement-summary', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'warehouseName' => $warehouseName,
            'stats' => $service->statsSummary($dateFrom, $dateTo, $warehouseId),
            'purchases' => $service->purchasesByWarehouseSummary($dateFrom, $dateTo, $warehouseId)->get(),
            'sales' => $service->salesByWarehouseSummary($dateFrom, $dateTo, $warehouseId)->get(),
            'supplierPayments' => $service->supplierPaymentsSummary($dateFrom, $dateTo, $warehouseId)->get(),
            'customerReceipts' => $service->customerReceiptsSummary($dateFrom, $dateTo, $warehouseId)->get(),
            'expenses' => $service->expensesSummary($dateFrom, $dateTo, $warehouseId)->get(),
            'salaries' => $service->salariesSummary($dateFrom, $dateTo, $warehouseId)->get(),
            'salesReturns' => $service->salesReturnsByDateSummary($dateFrom, $dateTo, $warehouseId)->get(),
            'purchaseReturns' => $service->purchaseReturnsByDateSummary($dateFrom, $dateTo, $warehouseId)->get(),
            'cashBoxes' => $service->cashBoxesSummary($dateFrom, $dateTo, $warehouseId)->get(),
            'service' => $service,
        ])->name('daily-movement-summary.pdf');
    }
}
