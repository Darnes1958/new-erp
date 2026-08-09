<?php

namespace App\Services\Pdf;

use App\Models\OurCompany;
use App\Services\Market\PaymentAccountLedgerReportService;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class PaymentAccountLedgerPdfService
{
    /**
     * @param  Collection<int, \App\Models\CashBoxLedgerEntry|\App\Models\BankAccountLedgerEntry>  $rows
     * @param  array{debit: float, credit: float, balance: float}  $periodTotals
     */
    public function movement(
        string $reportTitle,
        string $accountName,
        Collection $rows,
        ?string $dateFrom,
        ?string $dateTo,
        float $openingBalance,
        array $periodTotals,
        ?OurCompany $company = null,
    ): PdfBuilder {
        return Pdf::view('pdf.market.payment-account-movement', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'reportTitle' => $reportTitle,
            'accountName' => $accountName,
            'rows' => $rows,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'openingBalance' => $openingBalance,
            'periodTotals' => $periodTotals,
            'service' => app(PaymentAccountLedgerReportService::class),
        ])->name('payment-account-movement.pdf');
    }
}
