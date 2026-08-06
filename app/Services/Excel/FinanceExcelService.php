<?php

namespace App\Services\Excel;

use App\Exports\Finance\FinanceMovementExport;
use App\Models\OurCompany;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinanceExcelService
{
    /**
     * @param  Collection<int, \App\Models\SalaryTransaction|\App\Models\RentTransaction|\App\Models\Expense>  $rows
     */
    public function movementReport(
        Collection $rows,
        string $reportTitle,
        string $kind,
        ?float $balance = null,
        ?string $subtitle = null,
        string $filename = 'finance-movement.xlsx',
    ): BinaryFileResponse {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new FinanceMovementExport(
                rows: $rows,
                company: OurCompany::forCurrentUser(),
                reportTitle: $reportTitle,
                kind: $kind,
                balance: $balance,
                subtitle: $subtitle,
            ),
            $filename,
        );
    }
}
