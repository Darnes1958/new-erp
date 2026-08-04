<?php

namespace App\Http\Middleware;

use App\Support\FilamentLogin;
use Filament\Http\Middleware\Authenticate as Middleware;

class FilamentAuthenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        return FilamentLogin::url($request);
    }
}
