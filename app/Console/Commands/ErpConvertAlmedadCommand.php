<?php

namespace App\Console\Commands;

use App\Services\Conversion\AlmedadDataConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ErpConvertAlmedadCommand extends Command
{
    protected $signature = 'erp:convert-almedad
        {source=ALMEDADSOFT_0001 : Almedad Soft source connection}
        {target=Bentaher3 : Target ERP company connection}
        {--fresh : Recreate company schema then convert}
        {--skip-migrate : Skip erp:migrate-company}
        {--only= : Comma-separated steps}';

    protected $description = 'Convert Almedad Soft data into Bentaher3 (sales then reconstructed installment contracts)';

    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $source = (string) $this->argument('source');
        $target = (string) $this->argument('target');
        $fresh = (bool) $this->option('fresh');
        $only = $this->option('only')
            ? array_map('trim', explode(',', (string) $this->option('only')))
            : null;

        if (! config("database.connections.{$source}")) {
            $this->error("Source connection [{$source}] is not configured.");

            return self::FAILURE;
        }

        if (! config("database.connections.{$target}")) {
            $this->error("Target connection [{$target}] is not configured.");

            return self::FAILURE;
        }

        try {
            $this->ensureTargetDatabase($target);

            if ($fresh || (! $this->option('skip-migrate') && ! Schema::connection($target)->hasTable('sales_invoices'))) {
                $this->info("Migrating company schema on [{$target}]".($fresh ? ' (fresh)' : '').'…');
                $exit = Artisan::call('erp:migrate-company', array_filter([
                    'connection' => $target,
                    '--fresh' => $fresh ?: null,
                ]));
                $this->line(trim(Artisan::output()));
                config(['database.default' => config('erp.central_connection', 'sqlsrv')]);

                if ($exit !== self::SUCCESS) {
                    return self::FAILURE;
                }
            }

            $this->info("Converting [{$source}] → [{$target}]");

            $converter = new AlmedadDataConverter($source, $target);
            $converter->convert($fresh && $this->option('skip-migrate'), $only);

            $this->info('Conversion completed.');
            $this->line('Issues: '.storage_path('app/almedad-conversion-issues.json'));
            $this->line('Count: '.count($converter->issues()));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->error($e->getFile().':'.$e->getLine());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function ensureTargetDatabase(string $target): void
    {
        $name = (string) config("database.connections.{$target}.database");
        $admin = (string) config('erp.central_connection', 'sqlsrv');

        $exists = DB::connection($admin)->selectOne(
            'SELECT DB_ID(?) AS id',
            [$name],
        );

        if (($exists->id ?? null) !== null) {
            return;
        }

        $this->warn("Creating database [{$name}]…");
        $safe = str_replace(']', ']]', $name);
        DB::connection($admin)->unprepared("CREATE DATABASE [{$safe}]");
    }
}
