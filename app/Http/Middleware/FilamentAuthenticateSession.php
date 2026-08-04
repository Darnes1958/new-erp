<?php

namespace App\Http\Middleware;

use App\Support\FilamentLogin;
use Filament\Http\Middleware\AuthenticateSession as Middleware;

class FilamentAuthenticateSession extends Middleware
{
    protected function redirectTo($request): ?string
    {
        return FilamentLogin::url($request);
    }
}
