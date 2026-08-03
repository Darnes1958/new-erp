<?php

namespace App\Services\Excel;

use App\Exports\Installments\StopsWithoutContractExport;
use App\Exports\Installments\WrongDeductionsExport;
use App\Models\InstallmentStopWithoutContract;
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
    public function wrongDeductionsReport(Collection $rows, array $filterLines = []): BinaryFileResponse
    {
        $reportDate = now()->toDateString();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new WrongDeductionsExport(
                rows: $rows->sortBy([
                    ['deduction_date', 'asc'],
                    ['id', 'asc'],
                ])->values(),
                company: OurCompany::forCurrentUser(),
                filterLines: $filterLines,
                reportDate: $reportDate,
            ),
            'wrong-deductions.xlsx',
        );
    }

    /**
     * @param  Collection<int, InstallmentStopWithoutContract>  $rows
     * @param  array<int, string>  $filterLines
     */
    public function stopsWithoutContractReport(Collection $rows, array $filterLines = []): BinaryFileResponse
    {
        $reportDate = now()->toDateString();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new StopsWithoutContractExport(
                rows: $rows->sortBy([
                    ['stop_date', 'asc'],
                    ['id', 'asc'],
                ])->values(),
                company: OurCompany::forCurrentUser(),
                filterLines: $filterLines,
                reportDate: $reportDate,
            ),
            'stops-without-contract.xlsx',
        );
    }
}
