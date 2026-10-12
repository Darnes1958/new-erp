<?php

namespace App\Services\Pdf;

use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\OurCompany;
use App\Services\Market\CustomerLedgerReportService;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class CustomerLedgerPdfService
{
    /**
     * @param  Collection<int, \App\Models\CustomerLedgerEntry>  $rows
     * @param  array{debit: float, credit: float, balance: float}  $periodTotals
     */
    public function customerMovement(
        Customer $customer,
        Collection $rows,
        string $dateFrom,
        float $openingBalance,
        array $periodTotals,
        ?OurCompany $company = null,
    ): PdfBuilder {
        return Pdf::view('pdf.market.customer-movement', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'customer' => $customer,
            'rows' => $rows,
            'dateFrom' => $dateFrom,
            'openingBalance' => $openingBalance,
            'periodTotals' => $periodTotals,
            'service' => app(CustomerLedgerReportService::class),
        ])->name('customer-movement.pdf');
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array{debit: float, credit: float, balance: float}  $summary
     */
    public function customerBalances(
        Collection $rows,
        string $reportTitle,
        array $summary,
        ?OurCompany $company = null,
    ): PdfBuilder {
        $typeNames = CustomerType::query()->pluck('name', 'id');
        $customerTypes = Customer::query()->pluck('customer_type_id', 'id');

        $rows = $rows->map(function ($row) use ($typeNames, $customerTypes) {
            $typeId = $customerTypes->get((int) $row->customer_id);
            $row->customer_type_name = $typeNames->get($typeId) ?? '—';

            return $row;
        });

        return Pdf::view('pdf.market.customer-balances', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'rows' => $rows,
            'reportTitle' => $reportTitle,
            'summary' => $summary,
        ])->name('customer-balances.pdf');
    }
}
