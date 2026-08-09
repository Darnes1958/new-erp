<?php

namespace App\Filament\Admin\Support;

use Illuminate\Support\Facades\Auth;

class SystemMonitorAccess
{
    public static function allowed(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->is_prog
            || $user->hasRole('admin')
            || $user->can('مراقبة التعديل');
    }
}
