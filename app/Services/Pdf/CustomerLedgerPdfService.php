<?php

namespace App\Services\Pdf;

use App\Models\Customer;
use App\Models\OurCompany;
use App\Services\Market\CustomerLedgerReportService;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class CustomerLedgerPdfService
{
    /**
     * @param  Collection<int, \App\Models\CustomerLedgerEntry>  $rows
     * @param  array{debit: float, credit: float, balance: float}  $periodTotals
     */
    public function customerMovement(
        Customer $customer,
        Collection $rows,
        string $dateFrom,
        float $openingBalance,
        array $periodTotals,
        ?OurCompany $company = null,
    ): PdfBuilder {
        return Pdf::view('pdf.market.customer-movement', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'customer' => $customer,
            'rows' => $rows,
            'dateFrom' => $dateFrom,
            'openingBalance' => $openingBalance,
            'periodTotals' => $periodTotals,
            'service' => app(CustomerLedgerReportService::class),
        ])->name('customer-movement.pdf');
    }
}
