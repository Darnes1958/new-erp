<?php

namespace App\Console\Commands;

use App\Services\Conversion\OurCompanyDataConverter;
use Illuminate\Console\Command;

class ErpConvertOurCompaniesCommand extends Command
{
    protected $signature = 'erp:convert-our-companies
        {source=InsFila : Legacy central database connection}';

    protected $description = 'Convert OurCompany records from legacy ERP into our_companies';

    public function handle(): int
    {
        $source = (string) $this->argument('source');

        $this->info("Converting our_companies from [{$source}]");

        try {
            (new OurCompanyDataConverter($source))->convert();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Our companies conversion completed successfully.');

        return self::SUCCESS;
    }
}
