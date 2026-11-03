<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'for_who',
    ];

    public function getConnectionName(): ?string
    {
        return (string) config('erp.central_connection', config('database.default'));
    }

    /**
     * @return array<string, string>
     */
    public static function forWhoOptions(): array
    {
        return Permission::forWhoOptions();
    }
}
