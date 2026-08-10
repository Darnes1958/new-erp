<?php

namespace App\Services\Conversion;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class OurCompanyDataConverter
{
    public function __construct(
        protected string $source = 'InsFila',
    ) {}

    public function convert(): void
    {
        $this->assertConnections();

        if (! $this->legacyTableExists()) {
            throw new RuntimeException("Legacy table [OurCompany] was not found on [{$this->source}].");
        }

        $this->log('Converting our_companies from legacy OurCompany...');

        $rows = DB::connection($this->source)
            ->table('OurCompany')
            ->orderBy('Company')
            ->get();

        if ($rows->isEmpty()) {
            $this->log('No legacy OurCompany rows found.');

            return;
        }

        foreach ($rows as $row) {
            $connectionName = trim((string) ($row->Company ?? ''));

            if ($connectionName === '') {
                continue;
            }

            if (! in_array($connectionName, config('erp.company_connections', []), true)) {
                $this->log("Skipping unknown connection [{$connectionName}]");

                continue;
            }

            DB::connection((string) config('erp.central_connection', 'sqlsrv'))->table('our_companies')->updateOrInsert(
                ['connection_name' => $connectionName],
                [
                    'display_name' => $this->stringOrNull($row->CompanyName ?? null) ?? $connectionName,
                    'display_name_suffix' => $this->stringOrNull($row->CompanyNameSuffix ?? null),
                    'comp_code' => $this->stringOrNull($row->CompCode ?? null),
                    'address' => $this->stringOrNull($row->address ?? $row->Address ?? null),
                    'phone' => $this->stringOrNull($row->phone ?? $row->Phone ?? null),
                    'is_active' => true,
                ],
            );

            $this->log("Migrated company [{$connectionName}]");
        }
    }

    protected function assertConnections(): void
    {
        foreach ([$this->source, (string) config('erp.central_connection', 'sqlsrv')] as $connection) {
            if (! config("database.connections.{$connection}")) {
                throw new RuntimeException("Database connection [{$connection}] is not configured.");
            }

            DB::connection($connection)->getPdo();
        }
    }

    protected function legacyTableExists(): bool
    {
        return DB::connection($this->source)->getSchemaBuilder()->hasTable('OurCompany');
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
