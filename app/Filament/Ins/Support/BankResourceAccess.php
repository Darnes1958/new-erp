<?php

namespace App\Filament\Ins\Support;

use Illuminate\Support\Facades\Auth;

class BankResourceAccess
{
    public static function canManage(): bool
    {
        $user = Auth::user();

        return $user?->is_prog
            || $user?->can('ادخال عقود')
            || $user?->can('تعديل عقود');
    }
}
