<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
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
        return [
            'sell' => 'مبيعات',
            'ins' => 'أقساط',
            'admin' => 'إدارة',
        ];
    }
}
