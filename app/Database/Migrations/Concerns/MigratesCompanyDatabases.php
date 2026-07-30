<?php

namespace App\Database\Migrations\Concerns;

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
}
