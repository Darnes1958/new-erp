<?php

namespace App\Services\Conversion\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait MapsLegacyEmpToUserId
{
    /** @var array<int, int> */
    protected array $empToUserId = [];

    protected bool $empToUserIdLoaded = false;

    protected function loadEmpToUserMap(): void
    {
        if ($this->empToUserIdLoaded) {
            return;
        }

        $this->empToUserId = $this->buildEmpToUserMap(
            $this->insCentralConnection(),
            $this->source,
        );
        $this->empToUserIdLoaded = true;
    }

    /**
     * @return array<int, int>
     */
    protected function buildEmpToUserMap(string $centralConnection, string $legacyCompany): array
    {
        if (! config("database.connections.{$centralConnection}")) {
            return [];
        }

        if (! Schema::connection($centralConnection)->hasTable('users')) {
            return [];
        }

        $targetCentral = $this->centralConnection();
        $query = DB::connection($centralConnection)
            ->table('users')
            ->where('company', $legacyCompany);

        if (! Schema::connection($centralConnection)->hasColumn('users', 'empno')) {
            return $query
                ->get(['id', 'email'])
                ->mapWithKeys(fn ($row) => [(int) $row->id => $this->resolveTargetUserId($targetCentral, (int) $row->id, $row->email)])
                ->filter()
                ->all();
        }

        $map = [];

        foreach ($query->whereNotNull('empno')->get(['id', 'email', 'empno']) as $row) {
            $targetUserId = $this->resolveTargetUserId($targetCentral, (int) $row->id, $row->email);

            if ($targetUserId !== null) {
                $map[(int) $row->empno] = $targetUserId;
            }
        }

        return $map;
    }

    protected function resolveTargetUserId(string $targetCentral, int $legacyUserId, mixed $email): ?int
    {
        $byId = DB::connection($targetCentral)->table('users')->where('id', $legacyUserId)->value('id');

        if ($byId !== null) {
            return (int) $byId;
        }

        if (! filled($email)) {
            return null;
        }

        $byEmail = DB::connection($targetCentral)->table('users')->where('email', $email)->value('id');

        return $byEmail !== null ? (int) $byEmail : null;
    }

    protected function resolveCreatedBy(mixed $emp): ?int
    {
        $this->loadEmpToUserMap();

        if ($emp === null || $emp === '') {
            return null;
        }

        $empNo = (int) $emp;

        if ($empNo === 0) {
            return null;
        }

        return $this->empToUserId[$empNo] ?? $this->resolveFallbackCreatedByUserId();
    }

    protected function resolveFallbackCreatedByUserId(): ?int
    {
        if (! property_exists($this, 'target') || ! filled($this->target)) {
            return null;
        }

        static $cache = [];

        if (array_key_exists($this->target, $cache)) {
            return $cache[$this->target];
        }

        $minId = DB::connection($this->centralConnection())
            ->table('users')
            ->where('company', $this->target)
            ->min('id');

        $cache[$this->target] = $minId !== null ? (int) $minId : null;

        return $cache[$this->target];
    }

    protected function centralConnection(): string
    {
        return (string) config('erp.central_connection', 'sqlsrv');
    }
}
