<?php

namespace App\Filament\Ins\Support;

use Illuminate\Support\Facades\Auth;

class BankResourceAccess
{
    public static function canManage(): bool
    {
        $user = Auth::user();

        return \App\Support\Authz::can('ادخال عقود', 'تعديل عقود', 'تقرير عن مصرف');
    }
}
