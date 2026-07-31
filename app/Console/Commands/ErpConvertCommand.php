<?php

namespace App\Console\Commands;

use App\Services\Conversion\CompanyDataConverter;
use Illuminate\Console\Command;

class ErpConvertCommand extends Command
{
    protected $signature = 'erp:convert
        {source=Motafoek : Old company database connection}
        {target=testERP : New company database connection}
        {--fresh : Clear target data before converting}
        {--only= : Comma-separated steps: company_settings,payment_methods,master,items,purchases,sales,fifo,payments,installments}';

    protected $description = 'Convert company data from old ERP schema to new ERP schema';

    public function handle(): int
    {
        $source = (string) $this->argument('source');
        $target = (string) $this->argument('target');
        $fresh = (bool) $this->option('fresh');
        $only = $this->option('only')
            ? array_map('trim', explode(',', (string) $this->option('only')))
            : null;

        $converter = new CompanyDataConverter($source, $target);

        $this->info("Converting [{$source}] -> [{$target}]");

        if ($only) {
            $this->line('Steps: '.implode(', ', $only));
        }

        try {
            $converter->convert($fresh, $only);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Conversion completed successfully.');

        return self::SUCCESS;
    }
}
