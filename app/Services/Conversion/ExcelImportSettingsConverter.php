<?php

namespace App\Services\Conversion;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ExcelImportSettingsConverter
{
    public function __construct(
        protected string $target,
        protected string $sourceCompany,
        protected ?string $adminConnection = null,
    ) {}

    public function convert(bool $replace = true): int
    {
        $this->assertConnections();

        $adminConnection = $this->adminConnection();

        if (! Schema::connection($adminConnection)->hasTable('excel_setings')) {
            throw new RuntimeException("Legacy table [excel_setings] was not found on [{$adminConnection}].");
        }

        if (! Schema::connection($this->target)->hasTable('bank_excel_import_settings')) {
            throw new RuntimeException("Target table [bank_excel_import_settings] was not found on [{$this->target}].");
        }

        $settings = DB::connection($adminConnection)
            ->table('excel_setings')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        if ($settings->isEmpty()) {
            $this->log('No rows found in [excel_setings].');

            return 0;
        }

        $payrollBankIds = DB::connection($this->target)
            ->table('payroll_banks')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->flip();

        if ($payrollBankIds->isEmpty()) {
            throw new RuntimeException("Target [{$this->target}] has no payroll banks. Convert installments first.");
        }

        $rows = $this->buildRows($adminConnection, $settings, $payrollBankIds);

        if ($rows === []) {
            $this->log('No bank_excel_import_settings rows matched for company ['.$this->sourceCompany.'].');

            return 0;
        }

        if ($replace) {
            DB::connection($this->target)->table('bank_excel_import_settings')->delete();
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::connection($this->target)->table('bank_excel_import_settings')->insert($chunk);
        }

        $this->log('Imported '.count($rows).' bank_excel_import_settings row(s) into ['.$this->target.'].');

        return count($rows);
    }

    /**
     * @param  Collection<int|string, object>  $settings
     * @param  Collection<int, int>  $payrollBankIds
     * @return list<array<string, mixed>>
     */
    protected function buildRows(string $adminConnection, Collection $settings, Collection $payrollBankIds): array
    {
        $rows = [];

        if (Schema::connection($adminConnection)->hasTable('company_tajmeehies')) {
            $mappings = DB::connection($adminConnection)
                ->table('company_tajmeehies')
                ->where('company', $this->sourceCompany)
                ->orderBy('id')
                ->get();

            foreach ($mappings as $mapping) {
                $row = $this->mapSettingRow(
                    $settings->get($mapping->bank_id),
                    (int) $mapping->taj_id,
                    $payrollBankIds,
                );

                if ($row !== null) {
                    $rows[$row['payroll_bank_id']] = $row;
                }
            }
        }

        if ($rows !== []) {
            return array_values($rows);
        }

        foreach ($settings as $setting) {
            $row = $this->mapSettingRow($setting, (int) $setting->id, $payrollBankIds);

            if ($row !== null) {
                $rows[$row['payroll_bank_id']] = $row;
            }
        }

        return array_values($rows);
    }

    /**
     * @param  Collection<int, int>  $payrollBankIds
     * @return array<string, mixed>|null
     */
    protected function mapSettingRow(?object $setting, int $payrollBankId, Collection $payrollBankIds): ?array
    {
        if ($setting === null || ! $payrollBankIds->has($payrollBankId)) {
            return null;
        }

        $name = $this->stringOrNull($setting->bank ?? null);
        $columnAccount = $this->stringOrNull($setting->acc ?? null);
        $columnCustomer = $this->stringOrNull($setting->name ?? null);
        $columnAmount = $this->stringOrNull($setting->ksm ?? null);
        $columnDate = $this->stringOrNull($setting->ksm_date ?? null);

        if ($name === null || $columnAccount === null || $columnCustomer === null || $columnAmount === null || $columnDate === null) {
            return null;
        }

        return [
            'name' => $name,
            'heading_row' => max(1, (int) ($setting->headRowNo ?? 1)),
            'column_account_number' => $columnAccount,
            'column_customer_name' => $columnCustomer,
            'column_amount' => $columnAmount,
            'column_deduction_date' => $columnDate,
            'payroll_bank_id' => $payrollBankId,
        ];
    }

    protected function adminConnection(): string
    {
        $connection = $this->adminConnection ?? config('erp.legacy_admin_connection', 'useradmin');

        if (! config("database.connections.{$connection}")) {
            $connection = config('erp.legacy_auth_connection', 'InsFila');
        }

        if (! config("database.connections.{$connection}")) {
            throw new RuntimeException('Legacy admin connection is not configured.');
        }

        return $connection;
    }

    protected function assertConnections(): void
    {
        foreach ([$this->adminConnection(), $this->target] as $connection) {
            if (! config("database.connections.{$connection}")) {
                throw new RuntimeException("Database connection [{$connection}] is not configured.");
            }

            DB::connection($connection)->getPdo();
        }
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    protected function log(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }
    }
}
