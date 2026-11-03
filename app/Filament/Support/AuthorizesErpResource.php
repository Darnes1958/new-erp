<?php

namespace App\Filament\Support;

use App\Support\Authz;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesErpResource
{
    public static function canAccess(): bool
    {
        return Authz::can(...static::accessPermissions());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return Authz::can(...static::createPermissions());
    }

    public static function canEdit(Model $record): bool
    {
        return Authz::can(...static::editPermissions());
    }

    public static function canDelete(Model $record): bool
    {
        return Authz::can(...static::deletePermissions());
    }

    /**
     * @return list<string>
     */
    protected static function accessPermissions(): array
    {
        return array_values(array_unique([
            ...static::createPermissions(),
            ...static::editPermissions(),
            ...static::deletePermissions(),
        ]));
    }

    /**
     * @return list<string>
     */
    protected static function createPermissions(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected static function editPermissions(): array
    {
        return static::createPermissions();
    }

    /**
     * @return list<string>
     */
    protected static function deletePermissions(): array
    {
        return [];
    }
}
