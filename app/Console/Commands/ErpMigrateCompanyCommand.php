<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

class ErpMigrateCompanyCommand extends Command
{
    protected $signature = 'erp:migrate-company
        {connection : Company database connection name (e.g. BenTaher_erp)}
        {--fresh : Drop all tables on the company database before migrating}';

    protected $description = 'Run company-schema migrations on a single ERP company database';

    public function handle(Migrator $migrator): int
    {
        $connection = (string) $this->argument('connection');

        if (! config("database.connections.{$connection}")) {
            $this->error("Database connection [{$connection}] is not configured.");

            return self::FAILURE;
        }

        if (($this->freshOption())) {
            $this->warn("Dropping all tables on [{$connection}]…");
            Artisan::call('db:wipe', [
                '--database' => $connection,
                '--force' => true,
            ]);
            $this->line(Artisan::output());
        }

        config(['erp.company_connections' => [$connection]]);

        $paths = $this->companyMigrationPaths();

        if ($paths === []) {
            $this->error('No company migration files were found.');

            return self::FAILURE;
        }

        $migrator->setConnection($connection);

        if (! $migrator->repositoryExists()) {
            Artisan::call('migrate:install', [
                '--database' => $connection,
            ]);
        }

        $this->info("Migrating company schema on [{$connection}]…");
        $this->line('Company migrations: '.count($paths));

        $migrator->run($paths);

        $this->info("Company migrations completed for [{$connection}].");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function companyMigrationPaths(): array
    {
        $directory = database_path('migrations');

        return collect(File::files($directory))
            ->filter(fn (SplFileInfo $file): bool => str_contains(
                File::get($file->getPathname()),
                'MigratesCompanyDatabases'
            ))
            ->sortBy(fn (SplFileInfo $file): string => $file->getFilename())
            ->map(fn (SplFileInfo $file): string => $file->getPathname())
            ->values()
            ->all();
    }

    protected function freshOption(): bool
    {
        return (bool) $this->option('fresh');
    }
}
