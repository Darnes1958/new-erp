<?php

namespace App\Support;

use Illuminate\Http\Request;

class FilamentLogin
{
    public static function url(?Request $request = null): string
    {
        $request ??= request();

        if ($request) {
            return rtrim($request->getSchemeAndHttpHost(), '/').'/market/login';
        }

        return route('filament.market.auth.login', absolute: false);
    }
}
