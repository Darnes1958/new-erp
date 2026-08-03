<?php

namespace App\Support;

final class Utf8Text
{
    public static function clean(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $cleaned = iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if ($cleaned !== false) {
            return $cleaned;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
