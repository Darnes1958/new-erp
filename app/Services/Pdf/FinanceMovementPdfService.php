<?php

namespace App\Services\Pdf;

use App\Models\OurCompany;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class FinanceMovementPdfService
{
    /**
     * @param  Collection<int, \App\Models\SalaryTransaction>  $rows
     */
    public function salaryMovement(
        Collection $rows,
        string $profileName,
        float $balance,
        ?OurCompany $company = null,
    ): PdfBuilder {
        return Pdf::view('pdf.finance.movement-report', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'reportTitle' => 'كشف حساب مرتب الموظف : '.$profileName,
            'balance' => $balance,
            'kind' => 'transaction',
            'rows' => $rows,
        ])->name('salary-movement.pdf');
    }

    /**
     * @param  Collection<int, \App\Models\RentTransaction>  $rows
     */
    public function rentMovement(
        Collection $rows,
        string $profileName,
        float $balance,
        ?OurCompany $company = null,
    ): PdfBuilder {
        return Pdf::view('pdf.finance.movement-report', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'reportTitle' => 'كشف حساب إيجار : '.$profileName,
            'balance' => $balance,
            'kind' => 'transaction',
            'rows' => $rows,
        ])->name('rent-movement.pdf');
    }

    /**
     * @param  Collection<int, \App\Models\Expense>  $rows
     */
    public function expenseMovement(
        Collection $rows,
        string $expenseTypeName,
        ?string $dateFrom,
        ?string $dateTo,
        ?OurCompany $company = null,
    ): PdfBuilder {
        $period = collect([$dateFrom, $dateTo])
            ->filter()
            ->implode(' — ');

        return Pdf::view('pdf.finance.movement-report', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'reportTitle' => 'حركة مصروفات : '.$expenseTypeName,
            'subtitle' => filled($period) ? 'الفترة : '.$period : null,
            'kind' => 'expense',
            'rows' => $rows,
        ])->name('expense-movement.pdf');
    }
}
