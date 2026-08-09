<?php

namespace App\Console\Commands;

use App\Services\Conversion\DeductionImportDateRangesConverter;
use App\Services\Conversion\ExcelImportSettingsConverter;
use Illuminate\Console\Command;

class ErpConvertExcelSettingsCommand extends Command
{
    protected $signature = 'erp:convert-excel-settings
        {target=testERP : New company database connection}
        {source? : Legacy company database connection (defaults to target)}
        {--company= : Legacy company name in useradmin.company_tajmeehies (defaults to source)}
        {--admin= : Legacy admin connection name (defaults to erp.legacy_admin_connection)}
        {--append : Keep existing import settings/date ranges rows}';

    protected $description = 'Convert useradmin.excel_setings and company dateofexcels into the new ERP import tables';

    public function handle(): int
    {
        $target = (string) $this->argument('target');
        $source = (string) ($this->argument('source') ?: $target);
        $company = (string) ($this->option('company') ?: $source);
        $admin = $this->option('admin') ? (string) $this->option('admin') : null;
        $replace = ! (bool) $this->option('append');

        $this->info("Converting Excel import data for [{$target}] (source={$source}, company={$company})");

        try {
            $settingsCount = (new ExcelImportSettingsConverter($target, $company, $admin))->convert($replace);
            $this->line("bank_excel_import_settings: {$settingsCount} row(s)");
        } catch (\Throwable $e) {
            $this->error('excel_setings: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            $rangesCount = (new DeductionImportDateRangesConverter($source, $target))->convert($replace);
            $this->line("deduction_import_date_ranges: {$rangesCount} row(s)");
        } catch (\Throwable $e) {
            $this->error('dateofexcels: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
