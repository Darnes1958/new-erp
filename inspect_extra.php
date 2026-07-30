<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$source = 'Motafoek';

foreach (['tarksts', 'hafithas', 'hafitha_trans', 'overkst_arcs', 'stops', 'tar_buys', 'tar_sells'] as $table) {
    echo "=== {$table} ===".PHP_EOL;
    $row = DB::connection($source)->table($table)->first();
    echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL.PHP_EOL;
}
