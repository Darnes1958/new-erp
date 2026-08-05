<?php

namespace App\Console\Commands;

use App\Support\CompanyConnections;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ErpBackfillBankMainsCommand extends Command
{
    protected $signature = 'erp:backfill-bank-mains
        {source : Legacy company DB connection (e.g. Motafoek)}
        {--target= : Target company connection (defaults to all company DBs)}';

    protected $description = 'Backfill bank_mains and payroll_banks.bank_main_id from legacy bank_mains/tajs';

    public function handle(): int
    {
        $source = (string) $this->argument('source');
        $targets = $this->option('target')
            ? [(string) $this->option('target')]
            : config('erp.company_connections', []);

        if (! CompanyConnections::isValid($source)) {
            $this->error("Invalid source connection [{$source}]");

            return self::FAILURE;
        }

        if (! Schema::connection($source)->hasTable('bank_mains')) {
            $this->error("Source [{$source}] has no bank_mains table");

            return self::FAILURE;
        }

        $bankMains = DB::connection($source)
            ->table('bank_mains')
            ->orderBy('id')
            ->get();

        $tajs = DB::connection($source)
            ->table('tajs')
            ->orderBy('id')
            ->get(['id', 'bank_main_id']);

        $updatedTargets = 0;

        foreach ($targets as $target) {
            if (! CompanyConnections::isValid($target)) {
                $this->warn("Skipping invalid target [{$target}]");

                continue;
            }

            if (! Schema::connection($target)->hasTable('bank_mains')) {
                $this->warn("Skipping [{$target}] — run migrations first");

                continue;
            }

            $this->backfillTarget($target, $bankMains->all(), $tajs->all());
            $updatedTargets++;
        }

        $this->info("Backfilled bank mains on {$updatedTargets} connection(s).");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, object>  $bankMains
     * @param  array<int, object>  $tajs
     */
    protected function backfillTarget(string $target, array $bankMains, array $tajs): void
    {
        DB::connection($target)->transaction(function () use ($target, $bankMains, $tajs): void {
            $connection = DB::connection($target);

            foreach ($bankMains as $row) {
                $payload = [
                    'name' => $row->name,
                    'r_type' => (int) ($row->r_type ?? 1),
                    'ratio' => $row->ratio ?? 0,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];

                $exists = $connection
                    ->table('bank_mains')
                    ->where('id', (int) $row->id)
                    ->exists();

                if ($exists) {
                    $connection
                        ->table('bank_mains')
                        ->where('id', (int) $row->id)
                        ->update($payload);

                    continue;
                }

                $connection->unprepared('SET IDENTITY_INSERT [bank_mains] ON');
                $connection->table('bank_mains')->insert([
                    'id' => (int) $row->id,
                    ...$payload,
                ]);
                $connection->unprepared('SET IDENTITY_INSERT [bank_mains] OFF');
            }

            foreach ($tajs as $row) {
                if (! isset($row->bank_main_id)) {
                    continue;
                }

                DB::connection($target)
                    ->table('payroll_banks')
                    ->where('id', (int) $row->id)
                    ->update(['bank_main_id' => $row->bank_main_id]);
            }
        });

        $this->line("Updated [{$target}] — ".count($bankMains).' bank main(s), '.count($tajs).' payroll link(s) checked.');
    }
}
