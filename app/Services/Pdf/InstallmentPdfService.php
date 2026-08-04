<?php

namespace App\Services\Pdf;

use App\Models\InstallmentStopWithoutContract;
use App\Models\InstallmentSurplus;
use App\Models\InstallmentSuspended;
use App\Models\OurCompany;
use App\Models\WrongDeduction;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class InstallmentPdfService
{
    /**
     * @param  Collection<int, WrongDeduction>  $rows
     * @param  array<int, string>  $filterLines
     */
    public function wrongDeductionsReport(Collection $rows, array $filterLines = [], ?string $reportTitle = null): PdfBuilder
    {
        return Pdf::view('pdf.installments.wrong-deductions', [
            'company' => OurCompany::forCurrentUser(),
            'filterLines' => $filterLines,
            'reportTitle' => $reportTitle ?? ('تقرير بالأقساط الواردة بالخطأ حتى تاريخ: '.now()->toDateString()),
            'rows' => $rows->sortBy([
                ['deduction_date', 'asc'],
                ['id', 'asc'],
            ])->values(),
        ])
            ->name('wrong-deductions.pdf');
    }

    /**
     * @param  Collection<int, InstallmentSurplus>  $rows
     * @param  array<int, string>  $filterLines
     */
    public function installmentSurplusesReport(Collection $rows, array $filterLines = [], ?string $reportTitle = null): PdfBuilder
    {
        return Pdf::view('pdf.installments.surpluses', [
            'company' => OurCompany::forCurrentUser(),
            'filterLines' => $filterLines,
            'reportTitle' => $reportTitle ?? ('تقرير بالأقساط المخصومة بالفائض حتى تاريخ: '.now()->toDateString()),
            'rows' => $rows->sortBy([
                ['surplus_date', 'asc'],
                ['id', 'asc'],
            ])->values(),
        ])
            ->name('installment-surpluses.pdf');
    }

    /**
     * @param  Collection<int, InstallmentSuspended>  $rows
     * @param  array<int, string>  $filterLines
     */
    public function installmentReturnsReport(Collection $rows, array $filterLines = [], ?string $reportTitle = null): PdfBuilder
    {
        return Pdf::view('pdf.installments.returns', [
            'company' => OurCompany::forCurrentUser(),
            'filterLines' => $filterLines,
            'reportTitle' => $reportTitle ?? ('تقرير بالأقساط المرجعة حتى تاريخ: '.now()->toDateString()),
            'rows' => $rows->sortBy([
                ['suspended_date', 'asc'],
                ['id', 'asc'],
            ])->values(),
        ])
            ->name('installment-returns.pdf');
    }

    /**
     * @param  Collection<int, InstallmentStopWithoutContract>  $rows
     * @param  array<int, string>  $filterLines
     */
    public function stopsWithoutContractReport(Collection $rows, array $filterLines = []): PdfBuilder
    {
        $reportDate = now()->toDateString();

        return Pdf::view('pdf.installments.stops-without-contract', [
            'company' => OurCompany::forCurrentUser(),
            'filterLines' => $filterLines,
            'reportDate' => $reportDate,
            'rows' => $rows->sortBy([
                ['stop_date', 'asc'],
                ['id', 'asc'],
            ])->values(),
        ])
            ->name('stops-without-contract.pdf');
    }
}
