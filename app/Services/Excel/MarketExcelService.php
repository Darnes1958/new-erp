<?php

namespace App\Services\Excel;

use App\Exports\Market\CustomerBalancesExport;
use App\Exports\Market\CustomerMovementExport;
use App\Exports\Market\ItemMovementExport;
use App\Exports\Market\SupplierBalancesExport;
use App\Exports\Market\SupplierMovementExport;
use App\Exports\Market\WarehouseStockExport;
use App\Models\OurCompany;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MarketExcelService
{
    /**
     * @param  Collection<int, \App\Models\CustomerLedgerEntry>  $rows
     * @param  array{debit: float, credit: float, balance: float}  $periodTotals
     */
    public function customerMovement(
        Collection $rows,
        string $customerName,
        string $dateFrom,
        float $openingBalance,
        array $periodTotals,
    ): BinaryFileResponse {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new CustomerMovementExport(
                rows: $rows,
                company: OurCompany::forCurrentUser(),
                customerName: $customerName,
                dateFrom: $dateFrom,
                openingBalance: $openingBalance,
                periodTotals: $periodTotals,
            ),
            'customer-movement.xlsx',
        );
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array{debit: float, credit: float, balance: float}  $summary
     */
    public function customerBalances(
        Collection $rows,
        string $reportTitle,
        array $summary,
    ): BinaryFileResponse {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new CustomerBalancesExport(
                rows: $rows,
                company: OurCompany::forCurrentUser(),
                reportTitle: $reportTitle,
                summary: $summary,
            ),
            'customer-balances.xlsx',
        );
    }

    /**
     * @param  Collection<int, \App\Models\SupplierLedgerEntry>  $rows
     * @param  array{debit: float, credit: float, balance: float}  $periodTotals
     */
    public function supplierMovement(
        Collection $rows,
        string $supplierName,
        string $dateFrom,
        float $openingBalance,
        array $periodTotals,
    ): BinaryFileResponse {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new SupplierMovementExport(
                rows: $rows,
                company: OurCompany::forCurrentUser(),
                supplierName: $supplierName,
                dateFrom: $dateFrom,
                openingBalance: $openingBalance,
                periodTotals: $periodTotals,
            ),
            'supplier-movement.xlsx',
        );
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array{debit: float, credit: float, balance: float}  $summary
     */
    public function supplierBalances(
        Collection $rows,
        string $reportTitle,
        array $summary,
    ): BinaryFileResponse {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new SupplierBalancesExport(
                rows: $rows,
                company: OurCompany::forCurrentUser(),
                reportTitle: $reportTitle,
                summary: $summary,
            ),
            'supplier-balances.xlsx',
        );
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array{warehouse_cost_total: float}  $summary
     */
    public function warehouseStock(
        Collection $rows,
        string $reportTitle,
        array $summary,
        ?string $warehouseName,
        bool $showCosts,
        bool $multiWarehouse,
    ): BinaryFileResponse {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new WarehouseStockExport(
                rows: $rows,
                company: OurCompany::forCurrentUser(),
                reportTitle: $reportTitle,
                summary: $summary,
                warehouseName: $warehouseName,
                showCosts: $showCosts,
                multiWarehouse: $multiWarehouse,
            ),
            'warehouse-stock.xlsx',
        );
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    public function itemMovement(
        Collection $rows,
        string $itemName,
        string $dateFrom,
        ?string $warehouseName,
    ): BinaryFileResponse {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new ItemMovementExport(
                rows: $rows,
                company: OurCompany::forCurrentUser(),
                itemName: $itemName,
                dateFrom: $dateFrom,
                warehouseName: $warehouseName,
            ),
            'item-movement.xlsx',
        );
    }
}
