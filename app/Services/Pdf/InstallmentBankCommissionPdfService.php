<?php

namespace App\Services\Pdf;

use App\Models\OurCompany;
use App\Models\PayrollBank;
use App\Services\Installments\InstallmentBankCommissionReportService;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class InstallmentBankCommissionPdfService
{
    /**
     * @param  Collection<int, PayrollBank>  $rows
     * @param  array{installments_count: int, collected_total: float, commission_total: float}  $summary
     */
    public function report(
        Collection $rows,
        array $summary,
        ?string $reportTitle = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?OurCompany $company = null,
    ): PdfBuilder {
        $service = app(InstallmentBankCommissionReportService::class);

        return Pdf::view('pdf.installments.bank-commission-report', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'reportTitle' => $reportTitle ?? $service->reportTitle($dateFrom, $dateTo),
            'rows' => $rows,
            'summary' => $summary,
            'commissionFor' => fn (PayrollBank $row): float => $service->commissionFor($row),
        ])
            ->landscape()
            ->name('bank-commission-report.pdf');
    }
}
