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
            if (! Schema::connection($connection)->hasTable('warehouse_stocks')) {
                return;
            }

            DB::connection($connection)->statement('IF OBJECT_ID(N\'warehouse_stock_report_entries\', N\'V\') IS NOT NULL DROP VIEW warehouse_stock_report_entries');

            DB::connection($connection)->statement(<<<'SQL'
CREATE VIEW warehouse_stock_report_entries AS
SELECT
    CAST(ws.warehouse_id AS varchar(20)) + '-' + CAST(ws.item_id AS varchar(20)) AS row_key,
    ws.warehouse_id,
    w.name AS warehouse_name,
    ws.item_id,
    i.name AS item_name,
    i.barcode AS item_barcode,
    CAST(COALESCE(total_stock.total_qty_primary, 0) AS decimal(14, 3)) AS total_qty_primary,
    CAST(ws.quantity_primary AS decimal(14, 3)) AS warehouse_qty_primary,
    CAST(
        CASE
            WHEN COALESCE(fifo_warehouse.fifo_value, 0) > 0 THEN fifo_warehouse.fifo_value
            ELSE ws.quantity_primary * COALESCE(i.default_buy_price, 0)
        END AS decimal(14, 3)
    ) AS warehouse_cost_total,
    CAST(
        CASE
            WHEN ws.quantity_primary > 0 THEN
                (
                    CASE
                        WHEN COALESCE(fifo_warehouse.fifo_value, 0) > 0 THEN fifo_warehouse.fifo_value
                        ELSE ws.quantity_primary * COALESCE(i.default_buy_price, 0)
                    END
                ) / ws.quantity_primary
            ELSE 0
        END AS decimal(14, 3)
    ) AS avg_unit_cost,
    CAST(
        CASE
            WHEN COALESCE(fifo_all.fifo_value, 0) > 0 THEN fifo_all.fifo_value
            ELSE COALESCE(total_stock.total_qty_primary, 0) * COALESCE(i.default_buy_price, 0)
        END AS decimal(14, 3)
    ) AS total_cost_all,
    CAST(COALESCE(i.default_buy_price, 0) AS decimal(14, 3)) AS catalog_buy_price,
    CAST(COALESCE(sell_price.price_primary, 0) AS decimal(14, 3)) AS sell_price_primary
FROM warehouse_stocks ws
INNER JOIN warehouses w ON w.id = ws.warehouse_id
INNER JOIN items i ON i.id = ws.item_id
LEFT JOIN (
    SELECT item_id, SUM(quantity_primary) AS total_qty_primary
    FROM warehouse_stocks
    GROUP BY item_id
) total_stock ON total_stock.item_id = ws.item_id
LEFT JOIN (
    SELECT
        pil.item_id,
        pi.warehouse_id,
        SUM(pil.remaining_qty_primary * pil.unit_cost_primary) AS fifo_value
    FROM purchase_invoice_lines pil
    INNER JOIN purchase_invoices pi ON pi.id = pil.purchase_invoice_id
    WHERE pil.remaining_qty_primary > 0
    GROUP BY pil.item_id, pi.warehouse_id
) fifo_warehouse ON fifo_warehouse.item_id = ws.item_id
    AND fifo_warehouse.warehouse_id = ws.warehouse_id
LEFT JOIN (
    SELECT
        pil.item_id,
        SUM(pil.remaining_qty_primary * pil.unit_cost_primary) AS fifo_value
    FROM purchase_invoice_lines pil
    WHERE pil.remaining_qty_primary > 0
    GROUP BY pil.item_id
) fifo_all ON fifo_all.item_id = ws.item_id
LEFT JOIN item_prices sell_price ON sell_price.item_id = i.id
    AND sell_price.payment_method_id = 1
    AND sell_price.price_kind = 'sell'
SQL);
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            DB::connection($connection)->statement('IF OBJECT_ID(N\'warehouse_stock_report_entries\', N\'V\') IS NOT NULL DROP VIEW warehouse_stock_report_entries');
        });
    }
};
