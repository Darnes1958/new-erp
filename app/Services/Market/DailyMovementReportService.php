<?php

namespace App\Services\Market;

use App\Enums\ReceiptTransactionKind;
use App\Models\CashBox;
use App\Models\CustomerReceipt;
use App\Models\Expense;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\RentTransaction;
use App\Models\SalaryTransaction;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Builder;

class DailyMovementReportService
{
    public function purchasesDetailQuery(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return PurchaseInvoice::query()
            ->select('purchase_invoices.*')
            ->selectRaw('(purchase_invoices.lines_subtotal - purchase_invoices.discount) as invoice_total')
            ->with(['supplier', 'warehouse'])
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('invoice_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('invoice_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId));
    }

    public function salesDetailQuery(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return SalesInvoice::query()
            ->with(['customer', 'warehouse'])
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('invoice_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('invoice_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId));
    }

    public function supplierPaymentsDetailQuery(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return SupplierPayment::query()
            ->with(['supplier', 'paymentMethod', 'bankAccount', 'cashBox', 'warehouse'])
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('payment_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('payment_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId));
    }

    public function customerReceiptsDetailQuery(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return CustomerReceipt::query()
            ->with(['customer', 'paymentMethod', 'bankAccount', 'cashBox', 'warehouse'])
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('receipt_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('receipt_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId));
    }

    public function salesReturnsDetailQuery(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return SalesReturn::query()
            ->with(['item', 'salesInvoice.customer', 'salesInvoice.warehouse'])
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('return_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('return_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query) => $query->whereHas(
                'salesInvoice',
                fn (Builder $invoiceQuery): Builder => $invoiceQuery->where('warehouse_id', $warehouseId),
            ));
    }

    public function purchaseReturnsDetailQuery(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return PurchaseReturn::query()
            ->with(['item', 'purchaseInvoice.supplier', 'purchaseInvoice.warehouse'])
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('return_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('return_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query) => $query->whereHas(
                'purchaseInvoice',
                fn (Builder $invoiceQuery): Builder => $invoiceQuery->where('warehouse_id', $warehouseId),
            ));
    }

    public function expensesDetailQuery(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return Expense::query()
            ->with(['expenseType', 'bankAccount', 'cashBox', 'warehouse'])
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('expense_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('expense_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId));
    }

    public function salariesDetailQuery(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return SalaryTransaction::query()
            ->with(['salaryProfile', 'bankAccount', 'cashBox'])
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('transaction_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('transaction_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query) => $query->whereHas(
                'salaryProfile',
                fn (Builder $profileQuery): Builder => $profileQuery->where('warehouse_id', $warehouseId),
            ));
    }

    public function rentsDetailQuery(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return RentTransaction::query()
            ->with(['rentProfile', 'bankAccount', 'cashBox'])
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('transaction_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('transaction_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query) => $query->whereHas(
                'rentProfile',
                fn (Builder $profileQuery): Builder => $profileQuery->where('warehouse_id', $warehouseId),
            ));
    }

    public function purchasesByWarehouseSummary(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return PurchaseInvoice::query()
            ->join('warehouses', 'warehouses.id', '=', 'purchase_invoices.warehouse_id')
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('purchase_invoices.invoice_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('purchase_invoices.invoice_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('purchase_invoices.warehouse_id', $warehouseId))
            ->selectRaw('warehouses.name as warehouse_name')
            ->selectRaw('SUM(purchase_invoices.lines_subtotal - purchase_invoices.discount) as total_amount')
            ->selectRaw('SUM(purchase_invoices.amount_paid) as paid_amount')
            ->selectRaw('SUM(purchase_invoices.balance) as balance_amount')
            ->groupBy('warehouses.name');
    }

    public function salesByWarehouseSummary(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return SalesInvoice::query()
            ->join('warehouses', 'warehouses.id', '=', 'sales_invoices.warehouse_id')
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('sales_invoices.invoice_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('sales_invoices.invoice_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('sales_invoices.warehouse_id', $warehouseId))
            ->selectRaw('warehouses.name as warehouse_name')
            ->selectRaw('SUM(sales_invoices.grand_total) as total_amount')
            ->selectRaw('SUM(sales_invoices.amount_paid) as paid_amount')
            ->selectRaw('SUM(sales_invoices.balance) as balance_amount')
            ->groupBy('warehouses.name');
    }

    public function supplierPaymentsSummary(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return $this->paymentSummaryQuery(
            SupplierPayment::query(),
            'payment_date',
            $dateFrom,
            $dateTo,
            $warehouseId,
        );
    }

    public function customerReceiptsSummary(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return $this->paymentSummaryQuery(
            CustomerReceipt::query(),
            'receipt_date',
            $dateFrom,
            $dateTo,
            $warehouseId,
        );
    }

    public function expensesSummary(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return Expense::query()
            ->join('expense_types', 'expense_types.id', '=', 'expenses.expense_type_id')
            ->leftJoin('bank_accounts', 'bank_accounts.id', '=', 'expenses.bank_account_id')
            ->leftJoin('cash_boxes', 'cash_boxes.id', '=', 'expenses.cash_box_id')
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('expenses.expense_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('expenses.expense_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('expenses.warehouse_id', $warehouseId))
            ->selectRaw('expense_types.name as expense_type_name')
            ->selectRaw('COALESCE(bank_accounts.name, cash_boxes.name, N\'—\') as payment_source_name')
            ->selectRaw('SUM(expenses.amount) as total_amount')
            ->groupBy('expense_types.name', 'bank_accounts.name', 'cash_boxes.name');
    }

    public function salariesSummary(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return SalaryTransaction::query()
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('transaction_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('transaction_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query) => $query->whereHas(
                'salaryProfile',
                fn (Builder $profileQuery): Builder => $profileQuery->where('warehouse_id', $warehouseId),
            ))
            ->select('transaction_type')
            ->selectRaw('SUM(amount) as total_amount')
            ->groupBy('transaction_type');
    }

    public function rentsSummary(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return RentTransaction::query()
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('transaction_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('transaction_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query) => $query->whereHas(
                'rentProfile',
                fn (Builder $profileQuery): Builder => $profileQuery->where('warehouse_id', $warehouseId),
            ))
            ->select('transaction_type')
            ->selectRaw('SUM(amount) as total_amount')
            ->groupBy('transaction_type');
    }

    public function salesReturnsByDateSummary(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return SalesReturn::query()
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('return_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('return_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query) => $query->whereHas(
                'salesInvoice',
                fn (Builder $invoiceQuery): Builder => $invoiceQuery->where('warehouse_id', $warehouseId),
            ))
            ->select('return_date')
            ->selectRaw('SUM(line_total) as total_amount')
            ->groupBy('return_date');
    }

    public function purchaseReturnsByDateSummary(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        return PurchaseReturn::query()
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('return_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('return_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query) => $query->whereHas(
                'purchaseInvoice',
                fn (Builder $invoiceQuery): Builder => $invoiceQuery->where('warehouse_id', $warehouseId),
            ))
            ->select('return_date')
            ->selectRaw('SUM(line_total) as total_amount')
            ->groupBy('return_date');
    }

    public function cashBoxesSummary(?string $dateFrom, ?string $dateTo, ?int $warehouseId): Builder
    {
        $customerIn = CustomerReceipt::query()
            ->whereNotNull('cash_box_id')
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('receipt_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('receipt_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId))
            ->select('cash_box_id')
            ->selectRaw('SUM(CASE WHEN flow_direction = 0 THEN amount ELSE 0 END) as inflow_amount')
            ->selectRaw('SUM(CASE WHEN flow_direction = 1 THEN amount ELSE 0 END) as outflow_amount')
            ->groupBy('cash_box_id');

        $supplierIn = SupplierPayment::query()
            ->whereNotNull('cash_box_id')
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('payment_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('payment_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId))
            ->select('cash_box_id')
            ->selectRaw('SUM(CASE WHEN flow_direction = 0 THEN amount ELSE 0 END) as inflow_amount')
            ->selectRaw('SUM(CASE WHEN flow_direction = 1 THEN amount ELSE 0 END) as outflow_amount')
            ->groupBy('cash_box_id');

        $expenseOut = Expense::query()
            ->whereNotNull('cash_box_id')
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('expense_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('expense_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId))
            ->select('cash_box_id')
            ->selectRaw('CAST(0 AS decimal(14, 3)) as inflow_amount')
            ->selectRaw('SUM(amount) as outflow_amount')
            ->groupBy('cash_box_id');

        $salaryOut = SalaryTransaction::query()
            ->whereNotNull('cash_box_id')
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('transaction_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('transaction_date', '<=', $dateTo))
            ->select('cash_box_id')
            ->selectRaw('CAST(0 AS decimal(14, 3)) as inflow_amount')
            ->selectRaw('SUM(amount) as outflow_amount')
            ->groupBy('cash_box_id');

        $rentOut = RentTransaction::query()
            ->whereNotNull('cash_box_id')
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('transaction_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('transaction_date', '<=', $dateTo))
            ->select('cash_box_id')
            ->selectRaw('CAST(0 AS decimal(14, 3)) as inflow_amount')
            ->selectRaw('SUM(amount) as outflow_amount')
            ->groupBy('cash_box_id');

        $union = $customerIn
            ->unionAll($supplierIn)
            ->unionAll($expenseOut)
            ->unionAll($salaryOut)
            ->unionAll($rentOut);

        return CashBox::query()
            ->joinSub($union, 'cash_movements', 'cash_movements.cash_box_id', '=', 'cash_boxes.id')
            ->selectRaw('cash_boxes.name as cash_box_name')
            ->selectRaw('SUM(cash_movements.inflow_amount) as inflow_amount')
            ->selectRaw('SUM(cash_movements.outflow_amount) as outflow_amount')
            ->selectRaw('SUM(cash_movements.inflow_amount - cash_movements.outflow_amount) as net_amount')
            ->groupBy('cash_boxes.name');
    }

    /**
     * @return array{
     *     purchases: float,
     *     sales: float,
     *     collections: float,
     *     payments: float,
     *     purchase_returns: float,
     *     sales_returns: float,
     *     expenses: float,
     *     net_cash_flow: float
     * }
     */
    public function statsSummary(?string $dateFrom, ?string $dateTo, ?int $warehouseId): array
    {
        $purchases = (float) PurchaseInvoice::query()
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('invoice_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('invoice_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId))
            ->selectRaw('COALESCE(SUM(lines_subtotal - discount), 0) as total')
            ->value('total');
        $sales = (float) SalesInvoice::query()
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('invoice_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('invoice_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId))
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total')
            ->value('total');
        $collections = (float) CustomerReceipt::query()
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('receipt_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('receipt_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId))
            ->where('flow_direction', 0)
            ->sum('amount')
            + (float) SupplierPayment::query()
                ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('payment_date', '>=', $dateFrom))
                ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('payment_date', '<=', $dateTo))
                ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId))
                ->where('flow_direction', 0)
                ->sum('amount');
        $payments = (float) CustomerReceipt::query()
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('receipt_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('receipt_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId))
            ->where('flow_direction', 1)
            ->sum('amount')
            + (float) SupplierPayment::query()
                ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('payment_date', '>=', $dateFrom))
                ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('payment_date', '<=', $dateTo))
                ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId))
                ->where('flow_direction', 1)
                ->sum('amount');
        $purchaseReturns = (float) PurchaseReturn::query()
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('return_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('return_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query) => $query->whereHas(
                'purchaseInvoice',
                fn (Builder $invoiceQuery): Builder => $invoiceQuery->where('warehouse_id', $warehouseId),
            ))
            ->sum('line_total');
        $salesReturns = (float) SalesReturn::query()
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('return_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('return_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query) => $query->whereHas(
                'salesInvoice',
                fn (Builder $invoiceQuery): Builder => $invoiceQuery->where('warehouse_id', $warehouseId),
            ))
            ->sum('line_total');
        $expenses = (float) Expense::query()
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('expense_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('expense_date', '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId))
            ->sum('amount');

        return [
            'purchases' => round($purchases, 3),
            'sales' => round($sales, 3),
            'collections' => round($collections, 3),
            'payments' => round($payments, 3),
            'purchase_returns' => round($purchaseReturns, 3),
            'sales_returns' => round($salesReturns, 3),
            'expenses' => round($expenses, 3),
            'net_cash_flow' => round($collections - $payments, 3),
        ];
    }

    public function paymentSourceLabel(?string $bankName, ?string $cashBoxName): string
    {
        return $bankName ?: ($cashBoxName ?: '—');
    }

    public function transactionKindLabel(int | ReceiptTransactionKind $kind): string
    {
        if ($kind instanceof ReceiptTransactionKind) {
            return $kind->getLabel();
        }

        return ReceiptTransactionKind::tryFrom($kind)?->getLabel() ?? (string) $kind;
    }

    private function paymentSummaryQuery(
        Builder $query,
        string $dateColumn,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $warehouseId,
    ): Builder {
        $table = $query->getModel()->getTable();

        return $query
            ->join('payment_methods', 'payment_methods.id', '=', "{$table}.payment_method_id")
            ->leftJoin('bank_accounts', 'bank_accounts.id', '=', "{$table}.bank_account_id")
            ->leftJoin('cash_boxes', 'cash_boxes.id', '=', "{$table}.cash_box_id")
            ->when(filled($dateFrom), fn (Builder $builder): Builder => $builder->whereDate("{$table}.{$dateColumn}", '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $builder): Builder => $builder->whereDate("{$table}.{$dateColumn}", '<=', $dateTo))
            ->when(filled($warehouseId), fn (Builder $builder): Builder => $builder->where("{$table}.warehouse_id", $warehouseId))
            ->selectRaw("{$table}.transaction_kind as transaction_kind")
            ->selectRaw('payment_methods.name as payment_method_name')
            ->selectRaw('bank_accounts.name as bank_account_name')
            ->selectRaw('cash_boxes.name as cash_box_name')
            ->selectRaw("SUM(CASE WHEN {$table}.flow_direction = 0 THEN {$table}.amount ELSE 0 END) as collection_amount")
            ->selectRaw("SUM(CASE WHEN {$table}.flow_direction = 1 THEN {$table}.amount ELSE 0 END) as payment_amount")
            ->groupBy("{$table}.transaction_kind", 'payment_methods.name', 'bank_accounts.name', 'cash_boxes.name');
    }
}
