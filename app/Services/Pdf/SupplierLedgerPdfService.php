<?php

namespace App\Services\Pdf;

use App\Models\OurCompany;
use App\Models\Supplier;
use App\Services\Market\SupplierLedgerReportService;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class SupplierLedgerPdfService
{
    /**
     * @param  Collection<int, \App\Models\SupplierLedgerEntry>  $rows
     * @param  array{debit: float, credit: float, balance: float}  $periodTotals
     */
    public function supplierMovement(
        Supplier $supplier,
        Collection $rows,
        string $dateFrom,
        float $openingBalance,
        array $periodTotals,
        ?OurCompany $company = null,
    ): PdfBuilder {
        return Pdf::view('pdf.market.supplier-movement', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'supplier' => $supplier,
            'rows' => $rows,
            'dateFrom' => $dateFrom,
            'openingBalance' => $openingBalance,
            'periodTotals' => $periodTotals,
            'service' => app(SupplierLedgerReportService::class),
        ])->name('supplier-movement.pdf');
    }
}
