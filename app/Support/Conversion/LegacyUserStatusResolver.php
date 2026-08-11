<?php

namespace App\Support\Conversion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maps legacy INS / InsFila user rows to ERP users.status (1 active, 0 disabled).
 *
 * useradmin: active row in bans (soft-deleted rows = unbanned) or users.banned_at.
 * InsFila: users.status column.
 */
class LegacyUserStatusResolver
{
    /** @var array<string, array<int, true>> */
    protected static array $activeBanCache = [];

    public function __construct(
        protected string $connection,
    ) {}

    public static function forConnection(string $connection): self
    {
        return new self($connection);
    }

    public function resolve(object $row): int
    {
        if (property_exists($row, 'status') && (int) $row->status === 0) {
            return 0;
        }

        $userId = property_exists($row, 'id') ? (int) $row->id : null;

        if ($userId !== null && $this->hasActiveBan($userId)) {
            return 0;
        }

        if (property_exists($row, 'banned_at') && filled($row->banned_at)) {
            return 0;
        }

        return property_exists($row, 'status') ? (int) $row->status : 1;
    }

    protected function hasActiveBan(int $userId): bool
    {
        if (! isset(self::$activeBanCache[$this->connection])) {
            self::$activeBanCache[$this->connection] = $this->loadActiveBannedUserIds();
        }

        return isset(self::$activeBanCache[$this->connection][$userId]);
    }

    /** @return array<int, true> */
    protected function loadActiveBannedUserIds(): array
    {
        if (! Schema::connection($this->connection)->hasTable('bans')) {
            return [];
        }

        $map = [];

        foreach (
            DB::connection($this->connection)
                ->table('bans')
                ->where('bannable_type', 'App\\Models\\User')
                ->whereNull('deleted_at')
                ->where(function ($query): void {
                    $query->whereNull('expired_at')
                        ->orWhere('expired_at', '>', now());
                })
                ->pluck('bannable_id') as $id
        ) {
            $map[(int) $id] = true;
        }

        return $map;
    }
}
