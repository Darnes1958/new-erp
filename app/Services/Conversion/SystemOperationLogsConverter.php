<?php

namespace App\Services\Conversion;

use App\Enums\SystemOperationAction;
use App\Support\SystemOperationType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SystemOperationLogsConverter
{
    /** @var array<int, int|null> */
    protected array $empToUserId = [];

    public function __construct(
        protected string $source,
        protected string $target,
    ) {}

    public function convert(bool $replace = true): int
    {
        $this->assertConnections();

        if (! Schema::connection($this->target)->hasTable('system_operation_logs')) {
            throw new RuntimeException("Target table [system_operation_logs] was not found on [{$this->target}].");
        }

        $sourceTable = $this->legacyTableName();

        if ($sourceTable === null) {
            $this->log("Skipping system_operation_logs: Operations not found on [{$this->source}].");

            return 0;
        }

        $legacyRows = DB::connection($this->source)
            ->table($sourceTable)
            ->orderBy('id')
            ->get();

        if ($legacyRows->isEmpty()) {
            $this->log('No rows found in ['.$sourceTable.'].');

            return 0;
        }

        $this->empToUserId = $this->buildEmpToUserMap();

        if ($replace) {
            DB::connection($this->target)->table('system_operation_logs')->delete();
        }

        $rows = [];

        foreach ($legacyRows as $legacyRow) {
            $action = SystemOperationAction::tryFromLegacy((string) ($legacyRow->Oper ?? ''));

            if ($action === null) {
                continue;
            }

            $rows[] = [
                'id' => (int) $legacyRow->id,
                'operation' => SystemOperationType::tryFromLegacy((string) ($legacyRow->Proce ?? '')),
                'action' => $action->value,
                'record_id' => (int) ($legacyRow->no ?? 0),
                'customer_id' => null,
                'item_id' => null,
                'user_id' => $this->resolveUserId($legacyRow->emp ?? null),
                'created_at' => $legacyRow->created_at ?? now(),
            ];
        }

        if ($rows === []) {
            $this->log('No system_operation_logs rows matched on ['.$this->target.'].');

            return 0;
        }

        $this->insertWithIdentity('system_operation_logs', $rows);

        $this->log('Converted '.count($rows).' system_operation_logs rows.');

        return count($rows);
    }

    protected function legacyTableName(): ?string
    {
        foreach (['Operations', 'operations'] as $table) {
            if (Schema::connection($this->source)->hasTable($table)) {
                return $table;
            }
        }

        return null;
    }

    /** @return array<int, int|null> */
    protected function buildEmpToUserMap(): array
    {
        $legacyConnection = config('erp.legacy_auth_connection', 'InsFila');

        if (! config("database.connections.{$legacyConnection}")) {
            return [];
        }

        if (! Schema::connection($legacyConnection)->hasTable('users')) {
            return [];
        }

        $query = DB::connection($legacyConnection)->table('users');

        if (Schema::connection($legacyConnection)->hasColumn('users', 'empno')) {
            return $query
                ->whereNotNull('empno')
                ->pluck('id', 'empno')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return $query
            ->pluck('id', 'id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    protected function resolveUserId(mixed $emp): ?int
    {
        if ($emp === null || $emp === '') {
            return null;
        }

        $empNo = (int) $emp;

        if ($empNo === 0) {
            return null;
        }

        return $this->empToUserId[$empNo] ?? null;
    }

    protected function insertWithIdentity(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::connection($this->target)->table($table)->insert($chunk);
        }

        $maxId = max(array_column($rows, 'id'));

        DB::connection($this->target)->statement(
            "DBCC CHECKIDENT ('{$table}', RESEED, {$maxId})"
        );
    }

    protected function assertConnections(): void
    {
        foreach ([$this->source, $this->target] as $connection) {
            if (! config("database.connections.{$connection}")) {
                throw new RuntimeException("Database connection [{$connection}] is not configured.");
            }

            DB::connection($connection)->getPdo();
        }
    }

    protected function log(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }
    }
}
