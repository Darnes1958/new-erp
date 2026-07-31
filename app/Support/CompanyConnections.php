<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompanyConnections
{
    /**
     * @return array<string, string> connection_name => label
     */
    public static function options(): array
    {
        $connections = config('erp.company_connections', []);

        if ($connections === []) {
            return [];
        }

        $labels = self::labels();

        return collect($connections)
            ->mapWithKeys(fn (string $connection) => [
                $connection => $labels->get($connection, $connection),
            ])
            ->all();
    }

    public static function isValid(string $connection): bool
    {
        return in_array($connection, config('erp.company_connections', []), true)
            && config("database.connections.{$connection}") !== null;
    }

    /**
     * @return Collection<string, string>
     */
    protected static function labels(): Collection
    {
        if (! self::ourCompaniesTableExists()) {
            return collect();
        }

        return DB::table('our_companies')
            ->where('is_active', true)
            ->pluck('display_name', 'connection_name');
    }

    protected static function ourCompaniesTableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('our_companies');
        } catch (\Throwable) {
            return false;
        }
    }
}
