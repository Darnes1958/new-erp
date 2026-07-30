<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Auth;

trait UsesCompanyConnection
{
    public function getConnectionName(): ?string
    {
        $company = Auth::user()?->company;

        if (is_string($company) && $company !== '' && config("database.connections.{$company}")) {
            return $company;
        }

        return parent::getConnectionName();
    }
}
