<?php

namespace App\Support;

class PublicStorageUrl
{
    public static function url(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        $base = rtrim((string) config('filesystems.disks.public.url', '/media'), '/');

        return "{$base}/{$path}";
    }
}
