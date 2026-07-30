<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$source = 'Motafoek';

$tables = DB::connection($source)->select("
    SELECT t.TABLE_NAME, p.rows AS row_count
    FROM INFORMATION_SCHEMA.TABLES t
    INNER JOIN sys.tables st ON st.name = t.TABLE_NAME
    INNER JOIN sys.partitions p ON st.object_id = p.object_id AND p.index_id IN (0,1)
    WHERE t.TABLE_TYPE = 'BASE TABLE' AND t.TABLE_SCHEMA = 'dbo'
    ORDER BY t.TABLE_NAME
");

foreach ($tables as $table) {
    echo str_pad($table->TABLE_NAME, 35).' '.number_format($table->row_count).PHP_EOL;
}

echo PHP_EOL.'Total tables: '.count($tables).PHP_EOL;
