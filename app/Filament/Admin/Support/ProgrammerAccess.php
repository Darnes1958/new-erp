<?php

namespace App\Filament\Admin\Support;

use Illuminate\Support\Facades\Auth;

class ProgrammerAccess
{
    public static function allowed(): bool
    {
        return (bool) Auth::user()?->is_prog;
    }
}
