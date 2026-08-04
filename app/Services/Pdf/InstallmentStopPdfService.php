<?php

namespace App\Services\Pdf;

use App\Models\InstallmentContract;
use App\Models\OurCompany;
use App\Models\PayrollBank;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class InstallmentStopPdfService
{
    /**
     * @param  Collection<int, InstallmentContract>  $contracts
     */
    public function collectiveReport(
        Collection $contracts,
        PayrollBank $payrollBank,
        ?OurCompany $company = null,
    ): PdfBuilder {
        return Pdf::view('pdf.installments.stop-collective', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'payrollBank' => $payrollBank,
            'reportDate' => now()->toDateString(),
            'rows' => $contracts->sortBy([
                ['id', 'asc'],
            ])->values(),
        ])
            ->name('installment-stop-collective.pdf');
    }

    public function individualReport(
        InstallmentContract $contract,
        PayrollBank $payrollBank,
        ?OurCompany $company = null,
    ): PdfBuilder {
        $contract->loadMissing(['customer', 'stop']);

        return Pdf::view('pdf.installments.stop-individual', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'contract' => $contract,
            'payrollBank' => $payrollBank,
            'reportDate' => now()->toDateString(),
        ])
            ->name('installment-stop-'.$contract->id.'.pdf');
    }
}
