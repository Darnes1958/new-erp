<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class MarketPanelLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $home = Filament::getPanel('market')->getUrl();
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && $this->isSafePostLoginUrl($intended)) {
            return redirect()->to($intended);
        }

        return redirect()->to($home);
    }

    protected function isSafePostLoginUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '/';

        return ! in_array(rtrim($path, '/'), ['', '/dashboard'], true);
    }
}
