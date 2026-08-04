<?php

namespace App\Services\Pdf;

use App\Models\InstallmentStopWithoutContract;
use App\Models\OurCompany;
use App\Models\PayrollBank;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class InstallmentStopWithoutContractPdfService
{
    /**
     * @param  Collection<int, InstallmentStopWithoutContract>  $rows
     */
    public function collectiveReport(
        Collection $rows,
        PayrollBank $payrollBank,
        ?OurCompany $company = null,
    ): PdfBuilder {
        return Pdf::view('pdf.installments.stop-without-contract-collective', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'payrollBank' => $payrollBank,
            'reportDate' => now()->toDateString(),
            'rows' => $rows->sortBy([
                ['id', 'asc'],
            ])->values(),
        ])
            ->name('stop-without-contract-collective.pdf');
    }

    public function individualReport(
        InstallmentStopWithoutContract $record,
        PayrollBank $payrollBank,
        ?OurCompany $company = null,
    ): PdfBuilder {
        $record->loadMissing('payrollBank');

        return Pdf::view('pdf.installments.stop-without-contract-individual', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'record' => $record,
            'payrollBank' => $payrollBank,
            'reportDate' => now()->toDateString(),
        ])
            ->name('stop-without-contract-'.$record->id.'.pdf');
    }
}
