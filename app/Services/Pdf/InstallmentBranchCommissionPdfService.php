<?php

namespace App\Services\Pdf;

use App\Models\OurCompany;
use App\Models\PayrollBank;
use App\Services\Installments\InstallmentBranchCommissionReportService;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class InstallmentBranchCommissionPdfService
{
    /**
     * @param  Collection<int, PayrollBank>  $rows
     * @param  array{installments_count: int, collected_total: float, commission_total: float}  $summary
     */
    public function report(
        Collection $rows,
        array $summary,
        ?string $reportTitle = null,
        ?OurCompany $company = null,
    ): PdfBuilder {
        $service = app(InstallmentBranchCommissionReportService::class);

        return Pdf::view('pdf.installments.branch-commission-report', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'reportTitle' => $reportTitle ?? 'عمولة الفروع',
            'rows' => $rows,
            'summary' => $summary,
            'commissionFor' => fn (PayrollBank $row): float => $service->commissionFor($row),
        ])
            ->landscape()
            ->name('branch-commission-report.pdf');
    }
}
