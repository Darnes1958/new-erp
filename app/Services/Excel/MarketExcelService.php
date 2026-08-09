<?php

namespace App\Services\Excel;

use App\Exports\Market\CustomerBalancesExport;
use App\Exports\Market\CustomerMovementExport;
use App\Exports\Market\DailyMovementDetailExport;
use App\Exports\Market\DailyMovementSummaryExport;
use App\Exports\Market\ItemMovementExport;
use App\Exports\Market\PaymentAccountMovementExport;
use App\Exports\Market\PaymentReceiptListExport;
use App\Exports\Market\PurchaseInvoicesExport;
use App\Exports\Market\SalesInvoicesExport;
use App\Exports\Market\SupplierBalancesExport;
use App\Exports\Market\SupplierMovementExport;
use App\Exports\Market\WarehouseStockExport;
use App\Enums\ReceiptListKind;
use App\Models\OurCompany;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Services\Market\DailyMovementReportService;
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

    public function dailyMovementDetail(
        DailyMovementReportService $service,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $warehouseId,
        ?string $warehouseName,
    ): BinaryFileResponse {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new DailyMovementDetailExport(
                company: OurCompany::forCurrentUser(),
                dateFrom: $dateFrom,
                dateTo: $dateTo,
                warehouseName: $warehouseName,
                purchases: $service->purchasesDetailQuery($dateFrom, $dateTo, $warehouseId)->get(),
                sales: $service->salesDetailQuery($dateFrom, $dateTo, $warehouseId)->get(),
                supplierPayments: $service->supplierPaymentsDetailQuery($dateFrom, $dateTo, $warehouseId)->get(),
                customerReceipts: $service->customerReceiptsDetailQuery($dateFrom, $dateTo, $warehouseId)->get(),
                salesReturns: $service->salesReturnsDetailQuery($dateFrom, $dateTo, $warehouseId)->get(),
                purchaseReturns: $service->purchaseReturnsDetailQuery($dateFrom, $dateTo, $warehouseId)->get(),
                expenses: $service->expensesDetailQuery($dateFrom, $dateTo, $warehouseId)->get(),
                salaries: $service->salariesDetailQuery($dateFrom, $dateTo, $warehouseId)->get(),
                rents: $service->rentsDetailQuery($dateFrom, $dateTo, $warehouseId)->get(),
            ),
            'daily-movement-detail.xlsx',
        );
    }

    public function dailyMovementSummary(
        DailyMovementReportService $service,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $warehouseId,
        ?string $warehouseName,
    ): BinaryFileResponse {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new DailyMovementSummaryExport(
                company: OurCompany::forCurrentUser(),
                dateFrom: $dateFrom,
                dateTo: $dateTo,
                warehouseName: $warehouseName,
                stats: $service->statsSummary($dateFrom, $dateTo, $warehouseId),
                purchases: $service->purchasesByWarehouseSummary($dateFrom, $dateTo, $warehouseId)->get(),
                sales: $service->salesByWarehouseSummary($dateFrom, $dateTo, $warehouseId)->get(),
                supplierPayments: $service->supplierPaymentsSummary($dateFrom, $dateTo, $warehouseId)->get(),
                customerReceipts: $service->customerReceiptsSummary($dateFrom, $dateTo, $warehouseId)->get(),
                expenses: $service->expensesSummary($dateFrom, $dateTo, $warehouseId)->get(),
                salaries: $service->salariesSummary($dateFrom, $dateTo, $warehouseId)->get(),
                rents: $service->rentsSummary($dateFrom, $dateTo, $warehouseId)->get(),
                salesReturns: $service->salesReturnsByDateSummary($dateFrom, $dateTo, $warehouseId)->get(),
                purchaseReturns: $service->purchaseReturnsByDateSummary($dateFrom, $dateTo, $warehouseId)->get(),
                cashBoxes: $service->cashBoxesSummary($dateFrom, $dateTo, $warehouseId)->get(),
            ),
            'daily-movement-summary.xlsx',
        );
    }

    /**
     * @param  Collection<int, SalesInvoice>  $rows
     * @param  array{
     *     dateFrom: ?string,
     *     dateTo: ?string,
     *     customerName: ?string,
     *     warehouseName: ?string,
     *     tabLabel: ?string,
     *     showProfit: bool
     * }  $meta
     */
    public function salesInvoices(Collection $rows, array $meta): BinaryFileResponse
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new SalesInvoicesExport(
                rows: $rows,
                company: OurCompany::forCurrentUser(),
                meta: $meta,
            ),
            'sales-invoices-report.xlsx',
        );
    }

    /**
     * @param  Collection<int, PurchaseInvoice>  $rows
     * @param  array{
     *     dateFrom: ?string,
     *     dateTo: ?string,
     *     supplierName: ?string,
     *     warehouseName: ?string
     * }  $meta
     */
    public function purchaseInvoices(Collection $rows, array $meta): BinaryFileResponse
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new PurchaseInvoicesExport(
                rows: $rows,
                company: OurCompany::forCurrentUser(),
                meta: $meta,
            ),
            'purchase-invoices-report.xlsx',
        );
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array{debit: float, credit: float, balance: float}  $periodTotals
     */
    public function paymentAccountMovement(
        Collection $rows,
        string $reportTitle,
        string $accountName,
        ?string $dateFrom,
        ?string $dateTo,
        float $openingBalance,
        array $periodTotals,
    ): BinaryFileResponse {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new PaymentAccountMovementExport(
                rows: $rows,
                company: OurCompany::forCurrentUser(),
                reportTitle: $reportTitle,
                accountName: $accountName,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
                openingBalance: $openingBalance,
                periodTotals: $periodTotals,
            ),
            'payment-account-movement.xlsx',
        );
    }

    /**
     * @param  Collection<int, \Illuminate\Database\Eloquent\Model>  $rows
     * @param  array{
     *     dateFrom: ?string,
     *     dateTo: ?string,
     *     partyName: ?string,
     *     warehouseName: ?string,
     *     kindFilterLabel: ?string
     * }  $meta
     */
    public function paymentReceiptList(
        Collection $rows,
        ReceiptListKind $kind,
        array $meta,
    ): BinaryFileResponse {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new PaymentReceiptListExport(
                rows: $rows,
                company: OurCompany::forCurrentUser(),
                kind: $kind,
                meta: $meta,
            ),
            $kind->downloadFileStem().'.xlsx',
        );
    }
}
