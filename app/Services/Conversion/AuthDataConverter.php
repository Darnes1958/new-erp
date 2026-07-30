<?php

namespace App\Services\Conversion;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class AuthDataConverter
{
    protected string $source;

    public function __construct(
        string $source = 'InsFila',
    ) {
        $this->source = $source;
    }

    public function convert(bool $fresh = false, ?string $company = null): void
    {
        $this->assertConnections();

        if ($fresh) {
            $this->clearTarget();
        }

        $this->convertRoles();
        $this->convertPermissions();
        $this->convertRolePermissions();
        $userIds = $this->convertUsers($company);

        $this->convertModelRoles($userIds);
        $this->convertModelPermissions($userIds);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function assertConnections(): void
    {
        foreach ([$this->source, config('database.default')] as $connection) {
            if (! config("database.connections.{$connection}")) {
                throw new RuntimeException("Database connection [{$connection}] is not configured.");
            }

            DB::connection($connection)->getPdo();
        }
    }

    protected function clearTarget(): void
    {
        $this->log('Clearing auth tables in target database...');

        DB::table('role_has_permissions')->delete();
        DB::table('model_has_roles')->delete();
        DB::table('model_has_permissions')->delete();
        DB::table('roles')->delete();
        DB::table('permissions')->delete();
        DB::table('users')->delete();
    }

    protected function convertRoles(): void
    {
        $this->log('Converting roles...');

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

        $this->insertWithIdentity('roles', $rows);
    }

    protected function convertPermissions(): void
    {
        $this->log('Converting permissions...');

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

        $this->insertWithIdentity('permissions', $rows);
    }

    protected function convertRolePermissions(): void
    {
        $this->log('Converting role permissions...');

        $rows = DB::connection($this->source)
            ->table('role_has_permissions')
            ->get()
            ->map(fn ($row) => [
                'permission_id' => (int) $row->permission_id,
                'role_id' => (int) $row->role_id,
            ])
            ->all();

        foreach ($rows as $row) {
            DB::table('role_has_permissions')->insert($row);
        }
    }

    /** @return list<int> */
    protected function convertUsers(?string $company): array
    {
        $this->log('Converting users...');

        $query = DB::connection($this->source)->table('users')->orderBy('id');

        if ($company) {
            $query->where('company', $company);
        }

        $rows = $query->get()->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'email_verified_at' => $row->email_verified_at,
            'password' => $row->password,
            'company' => $row->company,
            'warehouse_id' => $row->place_id,
            'status' => $row->status ?? 1,
            'remember_token' => $row->remember_token,
            'is_prog' => (bool) ($row->is_prog ?? false),
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all();

        $this->insertWithIdentity('users', $rows);

        return array_column($rows, 'id');
    }

    /** @param list<int> $userIds */
    protected function convertModelRoles(array $userIds): void
    {
        $this->log('Converting user roles...');

        $query = DB::connection($this->source)
            ->table('model_has_roles')
            ->where('model_type', 'App\Models\User');

        if ($userIds !== []) {
            $query->whereIn('model_id', $userIds);
        }

        foreach ($query->get() as $row) {
            DB::table('model_has_roles')->insert([
                'role_id' => (int) $row->role_id,
                'model_type' => 'App\\Models\\User',
                'model_id' => (int) $row->model_id,
            ]);
        }
    }

    /** @param list<int> $userIds */
    protected function convertModelPermissions(array $userIds): void
    {
        $this->log('Converting user permissions...');

        $query = DB::connection($this->source)
            ->table('model_has_permissions')
            ->where('model_type', 'App\Models\User');

        if ($userIds !== []) {
            $query->whereIn('model_id', $userIds);
        }

        foreach ($query->get() as $row) {
            DB::table('model_has_permissions')->insert([
                'permission_id' => (int) $row->permission_id,
                'model_type' => 'App\\Models\\User',
                'model_id' => (int) $row->model_id,
            ]);
        }
    }

    protected function insertWithIdentity(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::transaction(function () use ($table, $chunk): void {
                DB::unprepared("SET IDENTITY_INSERT [{$table}] ON");

                foreach ($chunk as $row) {
                    DB::table($table)->insert($row);
                }

                DB::unprepared("SET IDENTITY_INSERT [{$table}] OFF");
            });
        }
    }

    protected function log(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }
    }
}
