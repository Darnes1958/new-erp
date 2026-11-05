<?php

namespace App\Services\Pdf;

use App\Models\OurCompany;
use App\Models\PayrollBank;
use App\Services\Installments\InstallmentCollectedTotalsReportService;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class InstallmentCollectedTotalsPdfService
{
    /**
     * @param  Collection<int, PayrollBank>  $rows
     * @param  array{collected_count: int, collected_total: float, expected_total: float, collection_ratio: float}  $summary
     */
    public function report(
        Collection $rows,
        array $summary,
        ?string $reportTitle = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?OurCompany $company = null,
    ): PdfBuilder {
        $service = app(InstallmentCollectedTotalsReportService::class);

        return Pdf::view('pdf.installments.collected-totals-report', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'reportTitle' => $reportTitle ?? $service->reportTitle($dateFrom, $dateTo),
            'rows' => $rows,
            'summary' => $summary,
            'collectedCountFor' => fn (PayrollBank $row): int => $service->collectedCountFor($row),
            'collectedTotalFor' => fn (PayrollBank $row): float => $service->collectedTotalFor($row),
            'expectedTotalFor' => fn (PayrollBank $row): float => $service->expectedTotalFor($row),
            'collectionRatioFor' => fn (PayrollBank $row): float => $service->collectionRatioFor($row),
        ])
            ->landscape()
            ->name('collected-totals-report.pdf');
    }
}
