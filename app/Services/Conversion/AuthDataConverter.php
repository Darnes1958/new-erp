<?php

namespace App\Services\Conversion;

use App\Support\Conversion\LegacyConnectionNaming;
use App\Support\Conversion\LegacyUserStatusResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class AuthDataConverter
{
    protected string $source;

    /** @var array<int, int> */
    protected array $roleIdMap = [];

    /** @var array<int, int> */
    protected array $permissionIdMap = [];

    protected LegacyUserStatusResolver $userStatusResolver;

    public function __construct(
        string $source = 'InsFila',
    ) {
        $this->source = $source;
        $this->userStatusResolver = LegacyUserStatusResolver::forConnection($source);
    }

    /**
     * Full auth import (all companies). Use once on a fresh central DB.
     */
    public function convert(bool $fresh = false, ?string $company = null, ?string $targetCompany = null): void
    {
        $this->assertConnections();
        $this->roleIdMap = [];
        $this->permissionIdMap = [];

        if ($company !== null) {
            $this->convertCompanyUsers(
                $company,
                $targetCompany ?? LegacyConnectionNaming::targetName($company),
                $fresh,
            );

            return;
        }

        if ($fresh) {
            $this->clearTarget();
        }

        $this->syncRoles();
        $this->syncPermissions();
        $this->syncRolePermissions();
        $userIds = $this->insertAllUsers();

        $this->syncModelRoles($userIds);
        $this->syncModelPermissions($userIds);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Import / refresh users for one legacy company (Electro → Electro_erp).
     * Safe to run per company; does not wipe other companies' users.
     */
    public function convertCompanyUsers(string $legacyCompany, string $targetCompany, bool $replaceCompanyUsers = false): void
    {
        $this->assertConnections();

        $this->log("Converting users for [{$legacyCompany}] → [{$targetCompany}]");

        $this->syncRoles();
        $this->syncPermissions();
        $this->syncRolePermissions();

        if ($replaceCompanyUsers) {
            DB::connection($this->centralConnection())->table('users')->where('company', $targetCompany)->delete();
        }

        $userIds = $this->upsertCompanyUsers($legacyCompany, $targetCompany);

        $this->syncModelRoles($userIds);
        $this->syncModelPermissions($userIds);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->log('Imported '.count($userIds)." user(s) for [{$legacyCompany}] → [{$targetCompany}].");
    }

    protected function assertConnections(): void
    {
        foreach ([$this->source, $this->centralConnection()] as $connection) {
            if (! config("database.connections.{$connection}")) {
                throw new RuntimeException("Database connection [{$connection}] is not configured.");
            }

            DB::connection($connection)->getPdo();
        }
    }

    protected function clearTarget(): void
    {
        $this->log('Clearing auth tables in target database...');

        $central = $this->central();

        $central->table('role_has_permissions')->delete();
        $central->table('model_has_roles')->delete();
        $central->table('model_has_permissions')->delete();
        $central->table('roles')->delete();
        $central->table('permissions')->delete();
        $central->table('users')->delete();
    }

    protected function syncRoles(): void
    {
        $rows = DB::connection($this->source)
            ->table('roles')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'guard_name' => $row->guard_name,
                'for_who' => $row->for_who ?? 'sell',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ])
            ->all();

        $missing = [];

        foreach ($rows as $row) {
            $legacyId = (int) $row['id'];

            $existing = $this->central()->table('roles')
                ->where('name', $row['name'])
                ->where('guard_name', $row['guard_name'])
                ->first();

            if ($existing) {
                $this->roleIdMap[$legacyId] = (int) $existing->id;

                continue;
            }

            if ($this->central()->table('roles')->where('id', $legacyId)->exists()) {
                $payload = $row;
                unset($payload['id']);
                $this->roleIdMap[$legacyId] = (int) $this->central()->table('roles')->insertGetId($payload);

                continue;
            }

            $missing[] = $row;
            $this->roleIdMap[$legacyId] = $legacyId;
        }

        $this->insertWithIdentity('roles', $missing);
    }

    protected function syncPermissions(): void
    {
        $rows = DB::connection($this->source)
            ->table('permissions')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'guard_name' => $row->guard_name,
                'for_who' => $row->for_who ?? 'sell',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ])
            ->all();

        $missing = [];

        foreach ($rows as $row) {
            $legacyId = (int) $row['id'];

            $existing = $this->central()->table('permissions')
                ->where('name', $row['name'])
                ->where('guard_name', $row['guard_name'])
                ->first();

            if ($existing) {
                $this->permissionIdMap[$legacyId] = (int) $existing->id;

                continue;
            }

            if ($this->central()->table('permissions')->where('id', $legacyId)->exists()) {
                $payload = $row;
                unset($payload['id']);
                $this->permissionIdMap[$legacyId] = (int) $this->central()->table('permissions')->insertGetId($payload);

                continue;
            }

            $missing[] = $row;
            $this->permissionIdMap[$legacyId] = $legacyId;
        }

        $this->insertWithIdentity('permissions', $missing);
    }

    protected function syncRolePermissions(): void
    {
        foreach (DB::connection($this->source)->table('role_has_permissions')->get() as $row) {
            $roleId = $this->mapRoleId((int) $row->role_id);
            $permissionId = $this->mapPermissionId((int) $row->permission_id);

            if (! $this->central()->table('roles')->where('id', $roleId)->exists()
                || ! $this->central()->table('permissions')->where('id', $permissionId)->exists()) {
                continue;
            }

            $payload = [
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ];

            $exists = $this->central()->table('role_has_permissions')
                ->where('permission_id', $payload['permission_id'])
                ->where('role_id', $payload['role_id'])
                ->exists();

            if (! $exists) {
                $this->central()->table('role_has_permissions')->insert($payload);
            }
        }
    }

    /** @return list<int> */
    protected function insertAllUsers(): array
    {
        $this->log('Converting users...');

        $rows = DB::connection($this->source)
            ->table('users')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => $this->legacyUserRowToPayload($row, (string) $row->company))
            ->all();

        $this->insertWithIdentity('users', $rows);

        return array_column($rows, 'id');
    }

    /** @return list<int> */
    protected function upsertCompanyUsers(string $legacyCompany, string $targetCompany): array
    {
        $legacyUsers = DB::connection($this->source)
            ->table('users')
            ->where('company', $legacyCompany)
            ->orderBy('id')
            ->get();

        if ($legacyUsers->isEmpty()) {
            $this->log("No users found for company [{$legacyCompany}] on [{$this->source}].");

            return [];
        }

        $userIds = [];

        foreach ($legacyUsers as $row) {
            $id = (int) $row->id;
            $payload = $this->legacyUserRowToPayload($row, $targetCompany);
            unset($payload['id']);

            $existingById = $this->central()->table('users')->where('id', $id)->exists();
            $existingByEmail = filled($row->email)
                && $this->central()->table('users')->where('email', $row->email)->exists();

            if ($existingById) {
                $this->central()->table('users')->where('id', $id)->update($payload);
                $userIds[] = $id;

                continue;
            }

            if ($existingByEmail) {
                $this->log("Skipped user id {$id}: email [{$row->email}] already exists.");

                continue;
            }

            $this->central()->transaction(function () use ($id, $payload): void {
                $this->central()->unprepared('SET IDENTITY_INSERT [users] ON');
                $this->central()->table('users')->insert(['id' => $id, ...$payload]);
                $this->central()->unprepared('SET IDENTITY_INSERT [users] OFF');
            });

            $userIds[] = $id;
        }

        return $userIds;
    }

    /**
     * @return array<string, mixed>
     */
    protected function legacyUserRowToPayload(object $row, string $company): array
    {
        return [
            'id' => (int) $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'email_verified_at' => $row->email_verified_at,
            'password' => $row->password,
            'company' => $company,
            'warehouse_id' => $row->place_id ?? null,
            'status' => $this->userStatusResolver->resolve($row),
            'remember_token' => $row->remember_token,
            'is_prog' => (bool) ($row->is_prog ?? false),
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            ...$this->conversionUserColumns($row),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function conversionUserColumns(object $row): array
    {
        if (! Schema::connection($this->centralConnection())->hasColumn('users', 'empno')) {
            return [];
        }

        $columns = ['old_user_id' => (int) $row->id];

        if (property_exists($row, 'empno') && $row->empno !== null) {
            $columns['empno'] = (int) $row->empno;
        }

        return $columns;
    }

    /** @param list<int> $userIds */
    protected function syncModelRoles(array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $this->log('Converting user roles...');

        $existingRoles = array_fill_keys(
            $this->central()->table('roles')->pluck('id')->all(),
            true,
        );

        foreach (
            DB::connection($this->source)
                ->table('model_has_roles')
                ->where('model_type', 'App\Models\User')
                ->whereIn('model_id', $userIds)
                ->get() as $row
        ) {
            $roleId = $this->mapRoleId((int) $row->role_id);
            $modelId = (int) $row->model_id;

            if (! isset($existingRoles[$roleId])) {
                continue;
            }

            $exists = $this->central()->table('model_has_roles')
                ->where('role_id', $roleId)
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $modelId)
                ->exists();

            if ($exists) {
                continue;
            }

            $this->central()->table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => 'App\\Models\\User',
                'model_id' => $modelId,
            ]);
        }
    }

    /** @param list<int> $userIds */
    protected function syncModelPermissions(array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $this->log('Converting user permissions...');

        $existingPermissions = array_fill_keys(
            $this->central()->table('permissions')->pluck('id')->all(),
            true,
        );

        foreach (
            DB::connection($this->source)
                ->table('model_has_permissions')
                ->where('model_type', 'App\Models\User')
                ->whereIn('model_id', $userIds)
                ->get() as $row
        ) {
            $permissionId = $this->mapPermissionId((int) $row->permission_id);
            $modelId = (int) $row->model_id;

            if (! isset($existingPermissions[$permissionId])) {
                continue;
            }

            $exists = $this->central()->table('model_has_permissions')
                ->where('permission_id', $permissionId)
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $modelId)
                ->exists();

            if ($exists) {
                continue;
            }

            $this->central()->table('model_has_permissions')->insert([
                'permission_id' => $permissionId,
                'model_type' => 'App\\Models\\User',
                'model_id' => $modelId,
            ]);
        }
    }

    protected function mapRoleId(int $legacyId): int
    {
        return $this->roleIdMap[$legacyId] ?? $legacyId;
    }

    protected function mapPermissionId(int $legacyId): int
    {
        return $this->permissionIdMap[$legacyId] ?? $legacyId;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function insertWithIdentity(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            $this->central()->transaction(function () use ($table, $chunk): void {
                $this->central()->unprepared("SET IDENTITY_INSERT [{$table}] ON");

                foreach ($chunk as $row) {
                    $this->central()->table($table)->insert($row);
                }

                $this->central()->unprepared("SET IDENTITY_INSERT [{$table}] OFF");
            });
        }
    }

    protected function centralConnection(): string
    {
        return (string) config('erp.central_connection', 'sqlsrv');
    }

    protected function central(): \Illuminate\Database\Connection
    {
        return DB::connection($this->centralConnection());
    }

    protected function log(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }
    }
}
