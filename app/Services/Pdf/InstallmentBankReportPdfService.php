<?php

namespace App\Services\Pdf;

use App\Enums\BankReportType;
use App\Models\OurCompany;
use App\Models\PayrollBank;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class InstallmentBankReportPdfService
{
    /**
     * @param  Collection<int, \App\Models\InstallmentContract|\App\Models\InstallmentDeduction>  $rows
     */
    public function report(
        BankReportType $type,
        Collection $rows,
        PayrollBank $payrollBank,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?OurCompany $company = null,
    ): PdfBuilder {
        return Pdf::view('pdf.installments.bank-report', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'type' => $type,
            'payrollBank' => $payrollBank,
            'reportTitle' => $type->pdfTitle($dateFrom, $dateTo),
            'rows' => $rows,
        ])
            ->name('bank-report-'.$type->value.'.pdf');
    }
}
