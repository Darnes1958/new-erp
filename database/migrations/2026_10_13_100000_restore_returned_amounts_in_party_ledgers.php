<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use App\Database\Views\CustomerLedgerEntriesView;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use MigratesCompanyDatabases;

    public function up(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            $this->rebuildCustomerLedger($connection, includeReturnedAmountInSalesInvoice: true);
            $this->rebuildSupplierLedger($connection, includeReturnedAmountInPurchaseInvoice: true);
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            $this->rebuildCustomerLedger($connection, includeReturnedAmountInSalesInvoice: false);
            $this->rebuildSupplierLedger($connection, includeReturnedAmountInPurchaseInvoice: false);
        });
    }

    protected function rebuildCustomerLedger(string $connection, bool $includeReturnedAmountInSalesInvoice): void
    {
        if (! $this->companyHasTables($connection, [
            'sales_invoices',
            'customer_receipts',
            'customers',
            'payment_methods',
            'sales_returns',
        ])) {
            return;
        }

        DB::connection($connection)->statement('IF OBJECT_ID(N\'customer_ledger_entries\', N\'V\') IS NOT NULL DROP VIEW customer_ledger_entries');
        DB::connection($connection)->statement(
            (new CustomerLedgerEntriesView)->createSql($connection, $includeReturnedAmountInSalesInvoice)
        );
    }

    protected function rebuildSupplierLedger(string $connection, bool $includeReturnedAmountInPurchaseInvoice): void
    {
        if (! $this->companyHasTables($connection, [
            'purchase_invoices',
            'supplier_payments',
            'suppliers',
            'payment_methods',
            'purchase_returns',
        ])) {
            return;
        }

        $purchaseDaen = $includeReturnedAmountInPurchaseInvoice
            ? 'CAST(pi.lines_subtotal - COALESCE(pi.discount, 0) + COALESCE(pr_tot.returned_total, 0) AS decimal(14, 3)) AS daen'
            : 'CAST(pi.lines_subtotal - COALESCE(pi.discount, 0) AS decimal(14, 3)) AS daen';

        $purchaseFrom = $includeReturnedAmountInPurchaseInvoice
            ? <<<'SQL'
    FROM purchase_invoices pi
    INNER JOIN suppliers s ON s.id = pi.supplier_id
    LEFT JOIN payment_methods pm ON pm.id = pi.payment_method_id
    LEFT JOIN (
        SELECT purchase_invoice_id, SUM(line_total) AS returned_total
        FROM purchase_returns
        GROUP BY purchase_invoice_id
    ) pr_tot ON pr_tot.purchase_invoice_id = pi.id
    WHERE pi.supplier_id IS NOT NULL
SQL
            : <<<'SQL'
    FROM purchase_invoices pi
    INNER JOIN suppliers s ON s.id = pi.supplier_id
    LEFT JOIN payment_methods pm ON pm.id = pi.payment_method_id
    WHERE pi.supplier_id IS NOT NULL
SQL;

        DB::connection($connection)->statement('IF OBJECT_ID(N\'supplier_ledger_entries\', N\'V\') IS NOT NULL DROP VIEW supplier_ledger_entries');

        DB::connection($connection)->statement(<<<SQL
CREATE VIEW supplier_ledger_entries AS
SELECT
    lines.supplier_id,
    lines.supplier_name,
    lines.rep_date,
    lines.source_id AS id,
    ROW_NUMBER() OVER (
        PARTITION BY lines.supplier_id
        ORDER BY lines.rep_date, lines.source_order, lines.sort_id
    ) AS idd,
    lines.transaction_kind,
    lines.payment_method_name,
    lines.mden,
    lines.daen,
    lines.notes
FROM (
    SELECT
        pi.supplier_id,
        s.name AS supplier_name,
        pi.invoice_date AS rep_date,
        pi.id AS source_id,
        CAST(8 AS int) AS transaction_kind,
        pm.name AS payment_method_name,
        CAST(0 AS decimal(14, 3)) AS mden,
        {$purchaseDaen},
        pi.notes,
        CAST(1 AS int) AS source_order,
        pi.id AS sort_id
{$purchaseFrom}

    UNION ALL

    SELECT
        sp.supplier_id,
        s.name,
        sp.payment_date,
        sp.id,
        sp.transaction_kind,
        pm.name,
        CASE WHEN sp.flow_direction = 1 THEN sp.amount ELSE CAST(0 AS decimal(14, 3)) END,
        CASE WHEN sp.flow_direction = 0 THEN sp.amount ELSE CAST(0 AS decimal(14, 3)) END,
        sp.notes,
        CAST(2 AS int),
        sp.id
    FROM supplier_payments sp
    INNER JOIN suppliers s ON s.id = sp.supplier_id
    LEFT JOIN payment_methods pm ON pm.id = sp.payment_method_id

    UNION ALL

    SELECT
        pi.supplier_id,
        s.name,
        pr.return_date,
        pr.id,
        CAST(16 AS int),
        pm.name,
        pr.line_total,
        CAST(0 AS decimal(14, 3)),
        pr.notes,
        CAST(3 AS int),
        pr.id
    FROM purchase_returns pr
    INNER JOIN purchase_invoices pi ON pi.id = pr.purchase_invoice_id
    INNER JOIN suppliers s ON s.id = pi.supplier_id
    LEFT JOIN payment_methods pm ON pm.id = pi.payment_method_id
    WHERE pi.supplier_id IS NOT NULL
) AS lines
SQL);
    }
};
