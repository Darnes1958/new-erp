<?php

namespace App\Console\Commands;

use App\Services\Conversion\InsCompanyDataConverter;
use App\Services\Conversion\InsSqlConversionRunner;
use App\Services\Installments\InstallmentContractMetricsService;
use App\Support\Conversion\LegacyConnectionNaming;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class InsConvertFullCommand extends Command
{
    protected $signature = 'ins:convert-full
        {legacy : Legacy INS company connection name (e.g. Elmaleh, BenTaher, Motahedon)}
        {--fresh : Drop and recreate target company schema, then convert from scratch}
        {--skip-migrate : Skip erp:migrate-company (schema already up to date)}
        {--skip-fifo : Skip FIFO rebuild (step 07)}
        {--only-sql : Skip PHP phases and run SQL scripts + FIFO + metrics (resume after SQL failure)}
        {--resume : Resume after a failed run — clears installment tables and re-imports installments + remaining steps}';

    protected $description = 'Full INS → ERP conversion in one command (schema, PHP data, SQL scripts, FIFO, metrics)';

    public function handle(InstallmentContractMetricsService $metrics): int
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $legacy = LegacyConnectionNaming::legacyName((string) $this->argument('legacy'));
        $target = LegacyConnectionNaming::targetName($legacy);

        $this->info("INS full conversion: [{$legacy}] → [{$target}]");
        $this->line('Prerequisite (once per company): erp:convert-auth useradmin --fresh --company='.$legacy.' --target-company='.$target);
        $this->newLine();

        if (! $this->assertConnections($legacy, $target)) {
            return self::FAILURE;
        }

        $resumeFromInstallments = (bool) $this->option('resume');
        $onlySql = (bool) $this->option('only-sql');

        try {
            if (! $onlySql) {
                if ($this->option('fresh')) {
                    $this->runPhase('Company migrations (fresh)', function () use ($target): void {
                        Artisan::call('erp:migrate-company', [
                            'connection' => $target,
                            '--fresh' => true,
                        ]);
                        $this->line(trim(Artisan::output()));
                        config(['database.default' => config('erp.central_connection', 'sqlsrv')]);
                    });
                } elseif (! $this->option('skip-migrate')) {
                    $this->runPhase('Company migrations', function () use ($target): void {
                        Artisan::call('erp:migrate-company', ['connection' => $target]);
                        $this->line(trim(Artisan::output()));
                        config(['database.default' => config('erp.central_connection', 'sqlsrv')]);
                    });
                }

                if (! $resumeFromInstallments) {
                    $this->runPhase('Register company', function () use ($legacy, $target): void {
                        $converter = new InsCompanyDataConverter($legacy, $target);
                        $converter->convert(only: ['register_company', 'company_settings']);
                    });

                    $this->runPhase('Core data (PHP)', function () use ($legacy, $target): void {
                        $converter = new InsCompanyDataConverter($legacy, $target);
                        $converter->convert(only: [
                            'payment_methods',
                            'master',
                            'items',
                            'sales',
                            'installments',
                            'users',
                            'created_by',
                        ]);
                    });
                } else {
                    $this->warn('Resuming from installments — clearing partial installment data first.');
                    $this->clearInstallmentTables($target);

                    $this->runPhase('Installments (PHP resume)', function () use ($legacy, $target): void {
                        $converter = new InsCompanyDataConverter($legacy, $target);
                        $converter->convert(only: ['installments', 'created_by']);
                    });
                }
            }

            $this->runPhase('User empno (SQL prerequisite)', function () use ($legacy, $target): void {
                $converter = new InsCompanyDataConverter($legacy, $target);
                $converter->ensureUserEmpnoForSqlScripts();
            });

            $this->runPhase('Extended data (SQL)', function () use ($legacy, $target): void {
                $runner = new InsSqlConversionRunner($legacy, $target);
                $runner->runAll(fn (string $message) => $this->line("  {$message}"));
            });

            if (! $this->option('skip-fifo')) {
                $this->runPhase('FIFO rebuild', function () use ($legacy, $target): void {
                    $this->runPhpScript('07_fifo_rebuild.php', [$legacy, $target]);
                });
            }

            $this->runPhase('Contract metrics', function () use ($metrics, $target): void {
                $count = $metrics->recalculateAll(connection: $target);
                $this->line("  Recalculated {$count} contract(s).");
            });

            $this->printSummary($legacy, $target);
        } catch (\Throwable $exception) {
            $this->error('Conversion failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Conversion completed successfully.');

        return self::SUCCESS;
    }

    protected function assertConnections(string $legacy, string $target): bool
    {
        foreach ([$legacy, $target, config('erp.ins_central_connection', 'useradmin')] as $connection) {
            if (! config("database.connections.{$connection}")) {
                $this->error("Database connection [{$connection}] is not configured in config/database.php");

                return false;
            }

            try {
                DB::connection($connection)->getPdo();
            } catch (\Throwable $exception) {
                $this->error("Cannot connect to [{$connection}]: ".$exception->getMessage());

                return false;
            }
        }

        if ($legacy === $target) {
            $this->error('Legacy and target connections must be different.');

            return false;
        }

        return true;
    }

    protected function runPhase(string $title, callable $callback): void
    {
        $this->info("▶ {$title}");
        $callback();
        $this->newLine();
    }

    /**
     * @param  list<string>  $arguments
     */
    protected function runPhpScript(string $filename, array $arguments = []): void
    {
        $script = database_path('conversion/ins/'.$filename);

        if (! is_file($script)) {
            throw new \RuntimeException("Script not found: {$script}");
        }

        $process = new Process(array_merge([PHP_BINARY, $script], $arguments), base_path());
        $process->setTimeout(null);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: "Script failed: {$filename}");
        }
    }

    protected function printSummary(string $legacy, string $target): void
    {
        $this->info('▶ Summary');

        $db = DB::connection($target);
        $checks = [
            'customers' => 'customers',
            'items' => 'items',
            'sales' => 'sales_invoices',
            'purchases' => 'purchase_invoices',
            'contracts' => 'installment_contracts',
            'deductions' => 'installment_deductions',
            'batches' => 'deduction_batches',
        ];

        foreach ($checks as $label => $table) {
            try {
                $count = $db->table($table)->count();
                $this->line(sprintf('  %-12s %s', $label.':', number_format($count)));
            } catch (\Throwable) {
                $this->line(sprintf('  %-12s (table missing)', $label.':'));
            }
        }

        $legacyDb = DB::connection($legacy)->getDatabaseName();
        $this->line('');
        $this->line("  Legacy DB: {$legacyDb}");
        $this->line("  Target DB: ".$db->getDatabaseName());
        $this->line("  Login: users with company = {$target}");
    }

    protected function clearInstallmentTables(string $target): void
    {
        $tables = [
            'installment_deduction_archives',
            'installment_deductions',
            'installment_contract_archives',
            'installment_contracts',
        ];

        DB::connection($target)->statement('EXEC sp_MSforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT ALL"');

        foreach ($tables as $table) {
            if (DB::connection($target)->getSchemaBuilder()->hasTable($table)) {
                DB::connection($target)->table($table)->delete();
                $this->line("  Cleared {$table}");
            }
        }

        DB::connection($target)->statement('EXEC sp_MSforeachtable "ALTER TABLE ? WITH CHECK CHECK CONSTRAINT ALL"');
    }
}
