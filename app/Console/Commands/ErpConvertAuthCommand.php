<?php

namespace App\Console\Commands;

use App\Services\Conversion\AuthDataConverter;
use Illuminate\Console\Command;

class ErpConvertAuthCommand extends Command
{
    protected $signature = 'erp:convert-auth
        {source=InsFila : Legacy central database connection}
        {--fresh : With --company: replace that company\'s users only; without: clear all auth tables}
        {--company= : Legacy company name in InsFila.users (e.g. Electro)}
        {--target-company= : New users.company value (default: legacy name + _erp suffix)}';

    protected $description = 'Convert users, roles and Spatie permissions from legacy ERP';

    public function handle(): int
    {
        $source = (string) $this->argument('source');
        $fresh = (bool) $this->option('fresh');
        $company = $this->option('company') ?: null;
        $targetCompany = $this->option('target-company') ?: null;
        $targetCompany = is_string($targetCompany) && $targetCompany !== '' ? $targetCompany : null;

        $converter = new AuthDataConverter($source);

        $this->info("Converting auth data from [{$source}]");

        if ($company) {
            $this->line("Users filter: {$company}");
            if ($targetCompany) {
                $this->line("Target company: {$targetCompany}");
            }
        }

        try {
            $converter->convert($fresh, $company, $targetCompany);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Auth conversion completed successfully.');

        return self::SUCCESS;
    }
}
