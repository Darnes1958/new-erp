<?php

namespace App\Console\Commands;

use App\Services\Conversion\InsCompanyDataConverter;
use App\Support\Conversion\LegacyConnectionNaming;
use Illuminate\Console\Command;

class InsConvertCommand extends Command
{
    protected $signature = 'ins:convert
        {legacy=BenTaher : Legacy INS connection name (unchanged, e.g. BenTaher)}
        {--target= : New ERP connection name (default: legacy name + configured suffix)}
        {--fresh : Clear target tables for the selected step(s) before converting}
        {--only= : Comma-separated steps: inspect,register_company,company_settings,payment_methods,master,items,sales,installments,users,created_by}';

    protected $description = 'Convert legacy INS company database into new ERP schema (step by step)';

    public function handle(): int
    {
        $legacy = LegacyConnectionNaming::legacyName((string) $this->argument('legacy'));
        $target = filled($this->option('target'))
            ? LegacyConnectionNaming::legacyName((string) $this->option('target'))
            : LegacyConnectionNaming::targetName($legacy);

        $only = $this->option('only')
            ? array_map('trim', explode(',', (string) $this->option('only')))
            : null;

        $naming = LegacyConnectionNaming::describe($legacy);

        $this->info("INS convert: [{$legacy}] -> [{$target}]");
        $this->line("Naming rule: {$naming['mode']} (suffix={$naming['suffix']}, prefix={$naming['prefix']})");
        $this->line('Legacy name is preserved; new ERP database uses the derived target name.');

        if ($only) {
            $this->line('Steps: '.implode(', ', $only));
        }

        $converter = new InsCompanyDataConverter($legacy, $target);

        try {
            $converter->convert((bool) $this->option('fresh'), $only);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('INS conversion step(s) completed.');

        return self::SUCCESS;
    }
}
