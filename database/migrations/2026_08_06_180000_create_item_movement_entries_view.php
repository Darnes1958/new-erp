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
            if (! Schema::connection($connection)->hasTable('purchase_invoice_lines')) {
                return;
            }

            DB::connection($connection)->statement('IF OBJECT_ID(N\'item_movement_entries\', N\'V\') IS NOT NULL DROP VIEW item_movement_entries');

            $transferUnion = Schema::connection($connection)->hasTable('warehouse_transfer_lines')
                ? <<<'SQL'

    UNION ALL

    SELECT
        CAST(N'نقل اصناف' AS nvarchar(50)) AS type,
        wtl.created_at,
        wtl.item_id,
        wt.transfer_date AS order_date,
        wt.id AS document_id,
        CAST(N'' AS nvarchar(255)) AS party_name,
        CAST(N'' AS nvarchar(50)) AS payment_method_name,
        wtl.qty_primary AS qty_primary,
        CAST(0 AS decimal(14, 3)) AS unit_price,
        CAST(0 AS decimal(14, 3)) AS line_total,
        CAST(N'إلي : ' + w_to.name AS nvarchar(255)) AS notes,
        CAST(N'من : ' + w_from.name AS nvarchar(255)) AS warehouse_name,
        wt.warehouse_from_id AS warehouse_id,
        CAST(N'T-' AS nvarchar(20)) + CAST(wtl.id AS nvarchar(20)) AS row_key,
        CAST(4 AS int) AS source_order
    FROM warehouse_transfer_lines wtl
    INNER JOIN warehouse_transfers wt ON wt.id = wtl.warehouse_transfer_id
    INNER JOIN warehouses w_from ON w_from.id = wt.warehouse_from_id
    INNER JOIN warehouses w_to ON w_to.id = wt.warehouse_to_id
SQL
                : '';

            DB::connection($connection)->statement(<<<SQL
CREATE VIEW item_movement_entries AS
SELECT
    lines.type,
    lines.created_at,
    lines.item_id,
    lines.order_date,
    lines.document_id AS id,
    lines.party_name AS name,
    lines.payment_method_name AS price_type,
    lines.qty_primary AS q1,
    lines.unit_price AS price1,
    lines.line_total AS sub_tot,
    lines.notes,
    lines.warehouse_name AS place_name,
    lines.warehouse_id AS place_id,
    lines.row_key,
    lines.source_order
FROM (
    SELECT
        CAST(N'مشتريات' AS nvarchar(50)) AS type,
        pil.created_at,
        pil.item_id,
        pi.invoice_date AS order_date,
        pi.id AS document_id,
        COALESCE(s.name, N'') AS party_name,
        COALESCE(pm.name, N'') AS payment_method_name,
        pil.qty_primary AS qty_primary,
        pil.unit_cost_primary AS unit_price,
        pil.line_cost_total AS line_total,
        COALESCE(pi.notes, N'') AS notes,
        w.name AS warehouse_name,
        pi.warehouse_id AS warehouse_id,
        CAST(N'P-' AS nvarchar(20)) + CAST(pil.id AS nvarchar(20)) AS row_key,
        CAST(1 AS int) AS source_order
    FROM purchase_invoice_lines pil
    INNER JOIN purchase_invoices pi ON pi.id = pil.purchase_invoice_id
    INNER JOIN warehouses w ON w.id = pi.warehouse_id
    LEFT JOIN suppliers s ON s.id = pi.supplier_id
    LEFT JOIN payment_methods pm ON pm.id = pi.payment_method_id
    WHERE pil.purchase_return_id IS NULL

    UNION ALL

    SELECT
        CAST(N'مبيعات' AS nvarchar(50)) AS type,
        sil.created_at,
        sil.item_id,
        si.invoice_date AS order_date,
        si.id AS document_id,
        COALESCE(c.name, N'') AS party_name,
        COALESCE(pm.name, N'') AS payment_method_name,
        sil.qty_primary AS qty_primary,
        sil.unit_price_primary AS unit_price,
        sil.line_total AS line_total,
        COALESCE(si.notes, N'') AS notes,
        w.name AS warehouse_name,
        si.warehouse_id AS warehouse_id,
        CAST(N'S-' AS nvarchar(20)) + CAST(sil.id AS nvarchar(20)) AS row_key,
        CAST(2 AS int) AS source_order
    FROM sales_invoice_lines sil
    INNER JOIN sales_invoices si ON si.id = sil.sales_invoice_id
    INNER JOIN warehouses w ON w.id = si.warehouse_id
    LEFT JOIN customers c ON c.id = si.customer_id
    LEFT JOIN payment_methods pm ON pm.id = si.payment_method_id
    WHERE sil.sales_return_id IS NULL

    UNION ALL

    SELECT
        CAST(N'ترجيع مبيعات' AS nvarchar(50)) AS type,
        sr.created_at,
        sr.item_id,
        sr.return_date AS order_date,
        sr.id AS document_id,
        COALESCE(c.name, N'') AS party_name,
        COALESCE(pm.name, N'') AS payment_method_name,
        sr.qty_primary AS qty_primary,
        sr.unit_price_primary AS unit_price,
        sr.line_total AS line_total,
        COALESCE(sr.notes, N'') AS notes,
        CAST(N'' AS nvarchar(255)) AS warehouse_name,
        CAST(0 AS bigint) AS warehouse_id,
        CAST(N'R-' AS nvarchar(20)) + CAST(sr.id AS nvarchar(20)) AS row_key,
        CAST(3 AS int) AS source_order
    FROM sales_returns sr
    INNER JOIN sales_invoices si ON si.id = sr.sales_invoice_id
    LEFT JOIN customers c ON c.id = si.customer_id
    LEFT JOIN payment_methods pm ON pm.id = si.payment_method_id
    {$transferUnion}
) lines
SQL);
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            DB::connection($connection)->statement('IF OBJECT_ID(N\'item_movement_entries\', N\'V\') IS NOT NULL DROP VIEW item_movement_entries');
        });
    }
};
