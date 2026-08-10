<?php

namespace App\Services\Conversion\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait ConvertsSqlServerCompanyData
{
    protected function legacyTable(string $table): Collection
    {
        return DB::connection($this->source)->table($table)->get();
    }

    protected function legacyTableOrdered(string $table, string $orderColumn): Collection
    {
        return DB::connection($this->source)->table($table)->orderBy($orderColumn)->get();
    }

    protected function legacyHasTable(string $table): bool
    {
        return DB::connection($this->source)->getSchemaBuilder()->hasTable($table);
    }

    protected function legacyHasColumn(string $table, string $column): bool
    {
        return DB::connection($this->source)->getSchemaBuilder()->hasColumn($table, $column);
    }

    protected function legacyMainArcRows(): Collection
    {
        $query = DB::connection($this->source)->table('MainArc');

        if ($this->legacyHasColumn('MainArc', 'id')) {
            return $query->orderBy('id')->get();
        }

        return $query->orderBy('no')->orderBy('order_no')->get();
    }

    protected function insertRows(string $table, array $rows): void
    {
        foreach ($rows as $row) {
            DB::connection($this->target)->table($table)->insert($row);
        }
    }

    protected function insertWithIdentity(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $connection = DB::connection($this->target);

        foreach (array_chunk($rows, 100) as $chunk) {
            $connection->unprepared("SET IDENTITY_INSERT [{$table}] ON");

            try {
                foreach ($chunk as $row) {
                    $connection->table($table)->insert($row);
                }
            } finally {
                $connection->unprepared("SET IDENTITY_INSERT [{$table}] OFF");
            }
        }
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return filled($value) ? (string) $value : null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function log(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }
    }
}
