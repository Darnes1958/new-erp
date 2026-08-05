<?php

namespace App\Services\Pdf;

use App\Models\InstallmentContract;
use App\Models\OurCompany;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class InstallmentContractPdfService
{
    /**
     * @param  Collection<int, \App\Models\InstallmentDeduction>  $deductions
     */
    public function summaryReport(
        InstallmentContract $contract,
        Collection $deductions,
        ?OurCompany $company = null,
    ): PdfBuilder {
        $contract->loadMissing(['customer', 'installmentBank']);

        return Pdf::view('pdf.installments.contract-summary', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'contract' => $contract,
            'deductions' => $deductions->sortBy([
                ['sequence', 'asc'],
                ['id', 'asc'],
            ])->values(),
        ])
            ->name('contract-'.$contract->id.'.pdf');
    }

    public function contractFormReport(
        InstallmentContract $contract,
        ?OurCompany $company = null,
    ): PdfBuilder {
        $contract->loadMissing(['customer', 'installmentBank.payrollBank', 'payrollBank']);

        $payrollBank = $contract->payrollBank ?? $contract->installmentBank?->payrollBank;

        return Pdf::view('pdf.installments.contract-form', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'contract' => $contract,
            'payrollBankName' => $payrollBank?->name ?? '',
            'payrollAccountNumber' => $payrollBank?->account_number ?? '',
            'periodFrom' => $this->monthYearLabel($contract->contract_start),
            'periodTo' => $this->monthYearLabel($contract->contract_end),
        ])
            ->name('contract-form-'.$contract->id.'.pdf');
    }

    protected function monthYearLabel(mixed $date): string
    {
        if (blank($date)) {
            return '';
        }

        $parsed = Carbon::parse($date);

        return $parsed->month.'-'.$parsed->year;
    }
}
