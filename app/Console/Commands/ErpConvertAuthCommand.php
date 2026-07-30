<?php

namespace App\Console\Commands;

use App\Services\Conversion\AuthDataConverter;
use Illuminate\Console\Command;

class ErpConvertAuthCommand extends Command
{
    protected $signature = 'erp:convert-auth
        {source=InsFila : Legacy central database connection}
        {--fresh : Clear users and permission tables before converting}
        {--company= : Import only users for this company (roles/permissions always full)}';

    protected $description = 'Convert users, roles and Spatie permissions from legacy ERP';

    public function handle(): int
    {
        $source = (string) $this->argument('source');
        $fresh = (bool) $this->option('fresh');
        $company = $this->option('company') ?: null;

        $converter = new AuthDataConverter($source);

        $this->info("Converting auth data from [{$source}]");

        if ($company) {
            $this->line("Users filter: {$company}");
        }

        try {
            $converter->convert($fresh, $company);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Auth conversion completed successfully.');

        return self::SUCCESS;
    }
}
