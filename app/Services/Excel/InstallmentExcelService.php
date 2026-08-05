<?php

namespace App\Services\Excel;

use App\Exports\Installments\BankCommissionExport;
use App\Exports\Installments\BankTotalsExport;
use App\Exports\Installments\BranchCommissionExport;
use App\Exports\Installments\InstallmentReturnsExport;
use App\Exports\Installments\InstallmentSurplusesExport;
use App\Exports\Installments\StopsWithoutContractExport;
use App\Exports\Installments\WrongDeductionsExport;
use App\Models\InstallmentBank;
use App\Models\PayrollBank;
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

    /**
     * @param  Collection<int, PayrollBank>  $rows
     */
    public function bankCommissionReport(Collection $rows, ?string $reportTitle = null): BinaryFileResponse
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new BankCommissionExport(
                rows: $rows->sortBy('name')->values(),
                company: OurCompany::forCurrentUser(),
                reportTitle: $reportTitle ?? 'تقرير عمولة المصارف',
            ),
            'bank-commission.xlsx',
        );
    }

    /**
     * @param  Collection<int, InstallmentBank|PayrollBank>  $rows
     */
    public function bankTotalsReport(Collection $rows, int $filterBy, ?string $reportTitle = null): BinaryFileResponse
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new BankTotalsExport(
                rows: $rows->sortBy('name')->values(),
                filterBy: $filterBy,
                company: OurCompany::forCurrentUser(),
                reportTitle: $reportTitle ?? 'إجمالي المصارف',
            ),
            'bank-totals.xlsx',
        );
    }

    /**
     * @param  Collection<int, PayrollBank>  $rows
     */
    public function branchCommissionReport(Collection $rows, ?string $reportTitle = null): BinaryFileResponse
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new BranchCommissionExport(
                rows: $rows->sortBy('name')->values(),
                company: OurCompany::forCurrentUser(),
                reportTitle: $reportTitle ?? 'عمولة الفروع',
            ),
            'branch-commission.xlsx',
        );
    }
}
