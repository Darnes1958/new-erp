<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use MigratesCompanyDatabases;

    public function up(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (! Schema::connection($connection)->hasTable('installment_deductions')) {
                return;
            }

            DB::connection($connection)->statement('IF OBJECT_ID(N\'customer_ledger_entries\', N\'V\') IS NOT NULL DROP VIEW customer_ledger_entries');

            DB::connection($connection)->statement(<<<'SQL'
CREATE VIEW customer_ledger_entries AS
SELECT
    lines.customer_id,
    lines.customer_name,
    lines.rep_date,
    lines.source_id AS id,
    ROW_NUMBER() OVER (
        PARTITION BY lines.customer_id
        ORDER BY lines.rep_date, lines.source_order, lines.sort_id
    ) AS idd,
    lines.transaction_kind,
    lines.payment_method_name,
    lines.mden,
    lines.daen,
    lines.notes
FROM (
    SELECT
        si.customer_id,
        c.name AS customer_name,
        si.invoice_date AS rep_date,
        si.id AS source_id,
        CAST(7 AS int) AS transaction_kind,
        pm.name AS payment_method_name,
        CAST(si.grand_total AS decimal(14, 3)) AS mden,
        CAST(0 AS decimal(14, 3)) AS daen,
        si.notes,
        CAST(1 AS int) AS source_order,
        si.id AS sort_id
    FROM sales_invoices si
    INNER JOIN customers c ON c.id = si.customer_id
    LEFT JOIN payment_methods pm ON pm.id = si.payment_method_id
    WHERE si.customer_id IS NOT NULL

    UNION ALL

    SELECT
        cr.customer_id,
        c.name,
        cr.receipt_date,
        cr.id,
        cr.transaction_kind,
        pm.name,
        CASE WHEN cr.flow_direction = 1 THEN cr.amount ELSE CAST(0 AS decimal(14, 3)) END,
        CASE WHEN cr.flow_direction = 0 THEN cr.amount ELSE CAST(0 AS decimal(14, 3)) END,
        cr.notes,
        CAST(2 AS int),
        cr.id
    FROM customer_receipts cr
    INNER JOIN customers c ON c.id = cr.customer_id
    LEFT JOIN payment_methods pm ON pm.id = cr.payment_method_id

    UNION ALL

    SELECT
        si.customer_id,
        c.name,
        sr.return_date,
        sr.id,
        CAST(15 AS int),
        pm.name,
        CAST(0 AS decimal(14, 3)),
        sr.line_total,
        sr.notes,
        CAST(3 AS int),
        sr.id
    FROM sales_returns sr
    INNER JOIN sales_invoices si ON si.id = sr.sales_invoice_id
    INNER JOIN customers c ON c.id = si.customer_id
    LEFT JOIN payment_methods pm ON pm.id = si.payment_method_id

    UNION ALL

    SELECT
        ic.customer_id,
        c.name,
        id.deduction_date,
        id.id,
        CAST(18 AS int),
        CASE id.deduction_type_id
            WHEN 1 THEN N'نقداً'
            WHEN 2 THEN N'المصرف'
            WHEN 3 THEN N'صك'
            WHEN 4 THEN N'إلكتروني'
            ELSE N'—'
        END,
        CAST(0 AS decimal(14, 3)),
        id.deducted_amount,
        COALESCE(id.notes, CONCAT(N'عقد رقم ', CAST(ic.id AS nvarchar(20)))),
        CAST(4 AS int),
        id.id
    FROM installment_deductions id
    INNER JOIN installment_contracts ic ON ic.id = id.installment_contract_id
    INNER JOIN customers c ON c.id = ic.customer_id
    WHERE ic.customer_id IS NOT NULL
) AS lines
SQL);
        });
    }

    public function down(): void
    {
        // Recreate the previous view without installment deductions.
        $this->onEachCompanyConnection(function (string $connection): void {
            if (! Schema::connection($connection)->hasTable('customer_receipts')) {
                return;
            }

            DB::connection($connection)->statement('IF OBJECT_ID(N\'customer_ledger_entries\', N\'V\') IS NOT NULL DROP VIEW customer_ledger_entries');

            DB::connection($connection)->statement(<<<'SQL'
CREATE VIEW customer_ledger_entries AS
SELECT
    lines.customer_id,
    lines.customer_name,
    lines.rep_date,
    lines.source_id AS id,
    ROW_NUMBER() OVER (
        PARTITION BY lines.customer_id
        ORDER BY lines.rep_date, lines.source_order, lines.sort_id
    ) AS idd,
    lines.transaction_kind,
    lines.payment_method_name,
    lines.mden,
    lines.daen,
    lines.notes
FROM (
    SELECT
        si.customer_id,
        c.name AS customer_name,
        si.invoice_date AS rep_date,
        si.id AS source_id,
        CAST(7 AS int) AS transaction_kind,
        pm.name AS payment_method_name,
        CAST(si.grand_total AS decimal(14, 3)) AS mden,
        CAST(0 AS decimal(14, 3)) AS daen,
        si.notes,
        CAST(1 AS int) AS source_order,
        si.id AS sort_id
    FROM sales_invoices si
    INNER JOIN customers c ON c.id = si.customer_id
    LEFT JOIN payment_methods pm ON pm.id = si.payment_method_id
    WHERE si.customer_id IS NOT NULL

    UNION ALL

    SELECT
        cr.customer_id,
        c.name,
        cr.receipt_date,
        cr.id,
        cr.transaction_kind,
        pm.name,
        CASE WHEN cr.flow_direction = 1 THEN cr.amount ELSE CAST(0 AS decimal(14, 3)) END,
        CASE WHEN cr.flow_direction = 0 THEN cr.amount ELSE CAST(0 AS decimal(14, 3)) END,
        cr.notes,
        CAST(2 AS int),
        cr.id
    FROM customer_receipts cr
    INNER JOIN customers c ON c.id = cr.customer_id
    LEFT JOIN payment_methods pm ON pm.id = cr.payment_method_id

    UNION ALL

    SELECT
        si.customer_id,
        c.name,
        sr.return_date,
        sr.id,
        CAST(15 AS int),
        pm.name,
        CAST(0 AS decimal(14, 3)),
        sr.line_total,
        sr.notes,
        CAST(3 AS int),
        sr.id
    FROM sales_returns sr
    INNER JOIN sales_invoices si ON si.id = sr.sales_invoice_id
    INNER JOIN customers c ON c.id = si.customer_id
    LEFT JOIN payment_methods pm ON pm.id = si.payment_method_id
) AS lines
SQL);
        });
    }
};
