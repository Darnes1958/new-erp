<?php

/**
 * INS → ERP — step 12: recalculate installment contract metrics
 *
 * Recomputes denormalized fields on installment_contracts from converted source
 * tables (deductions, surplus, suspended), using InstallmentContractMetricsService.
 *
 * Usage: php database/conversion/ins/12_recalculate_contract_metrics.php BenTaher_erp
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Installments\InstallmentContractMetricsService;

$target = $argv[1] ?? 'BenTaher_erp';

if (! config("database.connections.{$target}")) {
    fwrite(STDERR, "Connection [{$target}] is not configured.".PHP_EOL);
    exit(1);
}

$metrics = app(InstallmentContractMetricsService::class);

echo "Recalculating installment contract metrics on [{$target}]...".PHP_EOL;

$count = $metrics->recalculateAll(connection: $target);

echo "Done: {$count} contract(s) recalculated.".PHP_EOL;

$db = DB::connection($target);

$sample = $db->table('installment_contracts')
    ->selectRaw('
        COUNT(*) AS contracts,
        SUM(CASE WHEN surplus_count > 0 THEN 1 ELSE 0 END) AS with_surplus,
        SUM(CASE WHEN suspended_count > 0 THEN 1 ELSE 0 END) AS with_suspended,
        SUM(CAST(total_paid AS FLOAT)) AS total_paid,
        SUM(CAST(balance AS FLOAT)) AS balance
    ')
    ->first();

echo sprintf(
    'Summary: %d contracts | surplus: %d | suspended: %d | total_paid: %.2f | balance: %.2f',
    (int) $sample->contracts,
    (int) $sample->with_surplus,
    (int) $sample->with_suspended,
    (float) $sample->total_paid,
    (float) $sample->balance,
).PHP_EOL;
