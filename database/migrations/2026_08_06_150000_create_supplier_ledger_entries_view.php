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
            if (! Schema::connection($connection)->hasTable('supplier_payments')) {
                return;
            }

            DB::connection($connection)->statement('IF OBJECT_ID(N\'supplier_ledger_entries\', N\'V\') IS NOT NULL DROP VIEW supplier_ledger_entries');

            DB::connection($connection)->statement(<<<'SQL'
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
        CAST(pi.lines_subtotal - COALESCE(pi.discount, 0) AS decimal(14, 3)) AS daen,
        pi.notes,
        CAST(1 AS int) AS source_order,
        pi.id AS sort_id
    FROM purchase_invoices pi
    INNER JOIN suppliers s ON s.id = pi.supplier_id
    LEFT JOIN payment_methods pm ON pm.id = pi.payment_method_id
    WHERE pi.supplier_id IS NOT NULL

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
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            DB::connection($connection)->statement('IF OBJECT_ID(N\'supplier_ledger_entries\', N\'V\') IS NOT NULL DROP VIEW supplier_ledger_entries');
        });
    }
};
