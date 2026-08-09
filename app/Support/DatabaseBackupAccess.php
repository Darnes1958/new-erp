<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

class DatabaseBackupAccess
{
    public static function allowed(): bool
    {
        $user = Auth::user();

        if (! $user || ! filled($user->company)) {
            return false;
        }

        return $user->is_prog || $user->hasRole('admin');
    }
}
