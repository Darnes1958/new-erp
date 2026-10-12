<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
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

    public static function signedCssColor(float|int|string|null $value): ?string
    {
        $amount = (float) ($value ?? 0);

        if ($amount < 0) {
            return '#dc2626';
        }

        if ($amount > 0) {
            return '#16a34a';
        }

        return null;
    }

    public static function signedExcelArgb(float|int|string|null $value): ?string
    {
        $amount = (float) ($value ?? 0);

        if ($amount < 0) {
            return 'FFDC2626';
        }

        if ($amount > 0) {
            return 'FF16A34A';
        }

        return null;
    }

    public static function signedHtml(float|int|string|null $value): HtmlString
    {
        $amount = (float) ($value ?? 0);
        $formatted = e(number_format($amount, 3, '.', ','));
        $color = static::signedCssColor($amount);

        if ($color === null) {
            return new HtmlString($formatted);
        }

        return new HtmlString('<span style="color: '.$color.';">'.$formatted.'</span>');
    }

    public static function moneySignedHtml(float|int|string|null $value): string
    {
        return (string) static::signedHtml($value);
    }
}
