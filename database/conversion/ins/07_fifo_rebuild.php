<?php

/**
 * INS → ERP — step 07: FIFO rebuild
 *
 * INS has no buy_sells table. Replays sales against purchase layers (FIFO)
 * per warehouse, same logic as SalesInventoryService::applySalesLine.
 *
 * INS note: purchases land in storage (st_no) while most sales are from
 * showrooms (10000+hall). Use company-wide FIFO per item (ignore warehouse).
 *
 * Usage: php database/conversion/ins/07_fifo_rebuild.php BenTaher BenTaher_erp
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$legacy = $argv[1] ?? 'BenTaher';
$target = $argv[2] ?? 'BenTaher_erp';

$db = DB::connection($target);

echo "FIFO rebuild: {$legacy} → {$target}".PHP_EOL;

$db->table('fifo_allocations')->delete();

$db->statement('
    UPDATE pil
    SET remaining_qty_primary = pil.qty_primary - ISNULL((
        SELECT SUM(pr.qty_primary)
        FROM purchase_returns AS pr
        WHERE pr.purchase_invoice_line_id = pil.id
    ), 0),
        updated_at = GETDATE()
    FROM purchase_invoice_lines AS pil
');

$lines = $db->table('sales_invoice_lines as sil')
    ->join('sales_invoices as si', 'si.id', '=', 'sil.sales_invoice_id')
    ->orderBy('si.invoice_date')
    ->orderBy('si.id')
    ->orderBy('sil.id')
    ->get([
        'sil.id',
        'sil.sales_invoice_id',
        'sil.item_id',
        'sil.qty_primary',
        'sil.unit_price_primary',
        'si.warehouse_id',
    ]);

$total = $lines->count();
$fifoRows = 0;
$failures = 0;
$processed = 0;

foreach ($lines as $line) {
    $processed++;
    $remaining = (float) $line->qty_primary;
    $profit = 0.0;

    if ($remaining <= 0) {
        continue;
    }

    $layers = $db->table('purchase_invoice_lines as pil')
        ->join('purchase_invoices as pi', 'pi.id', '=', 'pil.purchase_invoice_id')
        ->where('pil.item_id', $line->item_id)
        ->where('pil.remaining_qty_primary', '>', 0)
        ->orderBy('pi.invoice_date')
        ->orderBy('pil.id')
        ->get(['pil.id', 'pil.purchase_invoice_id', 'pil.remaining_qty_primary', 'pil.unit_cost_primary']);

    foreach ($layers as $layer) {
        if ($remaining <= 0.0001) {
            break;
        }

        $available = (float) $layer->remaining_qty_primary;
        $take = min($available, $remaining);
        $unitCost = (float) $layer->unit_cost_primary;

        $db->table('purchase_invoice_lines')
            ->where('id', $layer->id)
            ->update([
                'remaining_qty_primary' => $available - $take,
                'updated_at' => now(),
            ]);

        $db->table('fifo_allocations')->insert([
            'purchase_invoice_id' => $layer->purchase_invoice_id,
            'purchase_invoice_line_id' => $layer->id,
            'sales_invoice_id' => $line->sales_invoice_id,
            'sales_invoice_line_id' => $line->id,
            'item_id' => $line->item_id,
            'qty_primary' => $take,
            'qty_secondary' => 0,
            'unit_cost' => $unitCost,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $profit += ((float) $line->unit_price_primary - $unitCost) * $take;
        $remaining -= $take;
        $fifoRows++;
    }

    if ($remaining > 0.0001) {
        $failures++;
    }

    $db->table('sales_invoice_lines')
        ->where('id', $line->id)
        ->update(['profit' => $profit, 'updated_at' => now()]);

    if ($processed % 2000 === 0) {
        echo "  … {$processed}/{$total}".PHP_EOL;
    }
}

echo "Done.".PHP_EOL;
echo "  sales lines processed : {$processed}".PHP_EOL;
echo "  fifo_allocations      : {$fifoRows}".PHP_EOL;
echo "  lines short on stock  : {$failures}".PHP_EOL;
