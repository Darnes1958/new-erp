<?php

namespace App\Services\Excel;

use App\Exports\Installments\InstallmentReturnsExport;
use App\Exports\Installments\InstallmentSurplusesExport;
use App\Exports\Installments\StopsWithoutContractExport;
use App\Exports\Installments\WrongDeductionsExport;
use App\Models\InstallmentStopWithoutContract;
use App\Models\InstallmentSurplus;
use App\Models\InstallmentSuspended;
use App\Models\OurCompany;
use App\Models\WrongDeduction;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InstallmentExcelService
{
    /**
     * @param  Collection<int, WrongDeduction>  $rows
     * @param  array<int, string>  $filterLines
     */
    public function wrongDeductionsReport(Collection $rows, array $filterLines = [], ?string $reportTitle = null): BinaryFileResponse
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new WrongDeductionsExport(
                rows: $rows->sortBy([
                    ['deduction_date', 'asc'],
                    ['id', 'asc'],
                ])->values(),
                company: OurCompany::forCurrentUser(),
                filterLines: $filterLines,
                reportTitle: $reportTitle ?? ('تقرير بالأقساط الواردة بالخطأ حتى تاريخ: '.now()->toDateString()),
            ),
            'wrong-deductions.xlsx',
        );
    }

    /**
     * @param  Collection<int, InstallmentSurplus>  $rows
     * @param  array<int, string>  $filterLines
     */
    public function installmentSurplusesReport(Collection $rows, array $filterLines = [], ?string $reportTitle = null): BinaryFileResponse
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new InstallmentSurplusesExport(
                rows: $rows->sortBy([
                    ['surplus_date', 'asc'],
                    ['id', 'asc'],
                ])->values(),
                company: OurCompany::forCurrentUser(),
                filterLines: $filterLines,
                reportTitle: $reportTitle ?? ('تقرير بالأقساط المخصومة بالفائض حتى تاريخ: '.now()->toDateString()),
            ),
            'installment-surpluses.xlsx',
        );
    }

    /**
     * @param  Collection<int, InstallmentSuspended>  $rows
     * @param  array<int, string>  $filterLines
     */
    public function installmentReturnsReport(Collection $rows, array $filterLines = [], ?string $reportTitle = null): BinaryFileResponse
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new InstallmentReturnsExport(
                rows: $rows->sortBy([
                    ['suspended_date', 'asc'],
                    ['id', 'asc'],
                ])->values(),
                company: OurCompany::forCurrentUser(),
                filterLines: $filterLines,
                reportTitle: $reportTitle ?? ('تقرير بالأقساط المرجعة حتى تاريخ: '.now()->toDateString()),
            ),
            'installment-returns.xlsx',
        );
    }

    /**
     * @param  Collection<int, InstallmentStopWithoutContract>  $rows
     * @param  array<int, string>  $filterLines
     */
    public function stopsWithoutContractReport(Collection $rows, array $filterLines = [], ?string $reportTitle = null): BinaryFileResponse
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new StopsWithoutContractExport(
                rows: $rows->sortBy([
                    ['stop_date', 'asc'],
                    ['id', 'asc'],
                ])->values(),
                company: OurCompany::forCurrentUser(),
                filterLines: $filterLines,
                reportTitle: $reportTitle ?? ('كشف إيقاف خصم بدون عقد حتى تاريخ: '.now()->toDateString()),
            ),
            'stops-without-contract.xlsx',
        );
    }
}
