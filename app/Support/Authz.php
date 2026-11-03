<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

final class Authz
{
    public static function can(string ...$permissions): bool
    {
        $user = Auth::user();

        if (! $user || $permissions === []) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public static function canViewBuyPrice(): bool
    {
        return self::can('سعر الشراء');
    }

    public static function isPurchaseCostMovementType(?string $type): bool
    {
        return in_array($type, ['مشتريات', 'ترجيع مشتريات'], true);
    }
}
