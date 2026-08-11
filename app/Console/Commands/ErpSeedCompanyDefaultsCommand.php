<?php

namespace App\Console\Commands;

use App\Services\Company\CompanyDefaultsSeeder;
use App\Support\CompanyConnections;
use App\Support\Conversion\LegacyConnectionNaming;
use Illuminate\Console\Command;

class ErpSeedCompanyDefaultsCommand extends Command
{
    protected $signature = 'erp:seed-company-defaults
        {connection? : Company database connection (e.g. Ekteyar or Ekteyar_erp)}
        {--connection= : Same as the connection argument (safer in Plesk / Windows shells)}
        {--register-central : Also register the company in our_companies + company_settings}
        {--display-name= : Display name for our_companies. Use underscores instead of spaces in shells that split Arabic text (e.g. الاختيار_الذكي)}
        {--force : Update existing default rows instead of skipping them}';

    protected $description = 'Seed default master data for a new empty ERP company (payment methods, customer/supplier, warehouses, units, cash/bank)';

    public function handle(CompanyDefaultsSeeder $seeder): int
    {
        $connection = $this->resolveConnection();

        if ($connection === null) {
            $this->error('Missing company connection. Example:');
            $this->line('  php artisan erp:seed-company-defaults Ekteyar_erp --register-central --display-name=الاختيار_الذكي');
            $this->line('  php artisan erp:seed-company-defaults --connection=Ekteyar_erp --register-central --display-name=الاختيار_الذكي');

            return self::FAILURE;
        }

        if (! config("database.connections.{$connection}")) {
            $this->error("Database connection [{$connection}] is not configured in config/database.php.");

            return self::FAILURE;
        }

        if (! CompanyConnections::isValid($connection)) {
            $this->warn("Connection [{$connection}] is not listed in config/erp.php → company_connections.");
            $this->warn('Add it there so migrations and the UI can discover this company.');
        }

        try {
            $seeder->seed($connection, (bool) $this->option('force'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('register-central')) {
            $displayName = $this->resolveDisplayName();

            $seeder->registerCentral($connection, $displayName);
            $this->info('Registered company in central database (our_companies + company_settings).');
        }

        $this->info("Default data seeded on [{$connection}].");

        $this->table(
            ['Table', 'ID', 'Label', 'Status'],
            collect($seeder->seededRows())
                ->map(fn (array $row): array => [$row['table'], $row['id'], $row['label'], 'created/updated'])
                ->merge(
                    collect($seeder->skippedRows())
                        ->map(fn (array $row): array => [$row['table'], $row['id'], $row['label'], 'skipped'])
                )
                ->all(),
        );

        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Create a user with company = '.$connection);
        $this->line('  2. Log in and start entering items / sales');

        if (! (bool) $this->option('register-central')) {
            $this->line('  Tip: re-run with --register-central to register in our_companies.');
        }

        return self::SUCCESS;
    }

    protected function resolveConnection(): ?string
    {
        $connection = $this->argument('connection') ?? $this->option('connection');
        $connection = is_string($connection) ? trim($connection) : '';

        if ($connection === '') {
            return null;
        }

        if (config("database.connections.{$connection}")) {
            return $connection;
        }

        $target = LegacyConnectionNaming::targetName($connection);

        if ($target !== $connection && config("database.connections.{$target}")) {
            $this->line("Using connection [{$target}] for company [{$connection}].");

            return $target;
        }

        return $connection;
    }

    protected function resolveDisplayName(): ?string
    {
        $displayName = $this->option('display-name');
        $displayName = is_string($displayName) ? trim($displayName) : '';

        if ($displayName === '') {
            return null;
        }

        if (! str_contains($displayName, ' ') && str_contains($displayName, '_')) {
            $displayName = str_replace('_', ' ', $displayName);
        }

        return $displayName;
    }
}
