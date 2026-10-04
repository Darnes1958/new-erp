<?php

namespace App\Database\Migrations\Concerns;

use Illuminate\Support\Facades\Schema;
use Throwable;

trait MigratesCompanyDatabases
{
    /**
     * @return list<string>
     */
    protected function companyConnections(): array
    {
        $configured = config('erp.company_connections');

        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        return collect(config('database.connections', []))
            ->filter(
                fn (array $connection, string $name) => ($connection['driver'] ?? null) === 'sqlsrv'
                    && ! in_array($name, ['sqlsrv', 'other'], true)
            )
            ->keys()
            ->values()
            ->all();
    }

    protected function onEachCompanyConnection(callable $callback): void
    {
        foreach ($this->companyConnections() as $connection) {
            $callback($connection);
        }
    }

    /**
     * True when the company DB is reachable and contains all required tables.
     *
     * @param  list<string>  $tables
     */
    protected function companyHasTables(string $connection, array $tables): bool
    {
        try {
            foreach ($tables as $table) {
                if (! Schema::connection($connection)->hasTable($table)) {
                    return false;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
