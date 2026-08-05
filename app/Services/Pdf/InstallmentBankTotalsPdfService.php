<?php

namespace App\Services\Pdf;

use App\Models\InstallmentBank;
use App\Models\OurCompany;
use App\Models\PayrollBank;
use App\Services\Installments\InstallmentBankTotalsReportService;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class InstallmentBankTotalsPdfService
{
    /**
     * @param  Collection<int, InstallmentBank|PayrollBank>  $rows
     * @param  array{
     *     contracts_count: int,
     *     contracts_total: float,
     *     total_paid: float,
     *     balance_total: float,
     *     surplus_total: float,
     *     suspended_total: float,
     *     wrong_total: float
     * }  $summary
     */
    public function report(
        Collection $rows,
        int $filterBy,
        array $summary,
        ?string $reportTitle = null,
        ?OurCompany $company = null,
    ): PdfBuilder {
        $service = app(InstallmentBankTotalsReportService::class);

        return Pdf::view('pdf.installments.bank-totals-report', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'filterBy' => $filterBy,
            'reportTitle' => $reportTitle ?? $service->reportTitle($filterBy),
            'nameHeading' => $filterBy === 2 ? 'المصرف التجميعي' : 'الاسم',
            'rows' => $rows,
            'summary' => $summary,
        ])
            ->landscape()
            ->name('bank-totals-report.pdf');
    }
}
