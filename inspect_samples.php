<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$source = 'Motafoek';

$samples = [
    'unitas' => DB::connection($source)->table('unitas')->get(),
    'unitbs' => DB::connection($source)->table('unitbs')->get(),
    'price_types' => DB::connection($source)->table('price_types')->get(),
    'mains' => DB::connection($source)->table('mains')->first(),
    'sell' => DB::connection($source)->table('sells')->first(),
];

foreach ($samples as $name => $data) {
    echo "=== {$name} ===".PHP_EOL;
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL.PHP_EOL;
}
