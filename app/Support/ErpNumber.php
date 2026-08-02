<?php

namespace App\Support;

use Illuminate\Support\Number;

final class ErpNumber
{
    public static function locale(): string
    {
        return (string) config('erp.number_locale', 'en_US');
    }

    public static function format(float|int|string|null $value, int $decimals = 3): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Number::format((float) $value, precision: $decimals, locale: static::locale());
    }

    public static function money(float|int|string|null $value): string
    {
        return static::format($value, 3);
    }

    public static function quantity(float|int|string|null $value, int $decimals = 3): string
    {
        return static::format($value, $decimals);
    }
}
