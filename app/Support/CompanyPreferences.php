<?php

namespace App\Support;

use App\Models\CompanyPreference;
use Illuminate\Support\Facades\Auth;

class CompanyPreferences
{
    public static function current(): ?CompanyPreference
    {
        $company = Auth::user()?->company;

        if (! is_string($company) || $company === '') {
            return null;
        }

        return static::forCompany($company);
    }

    public static function forCompany(string $company): CompanyPreference
    {
        return CompanyPreference::query()->firstOrCreate(
            ['company' => $company],
            ['print_after_store_sales' => false],
        );
    }

    public static function printAfterStoreSales(?string $company = null): bool
    {
        $company ??= Auth::user()?->company;

        if (! is_string($company) || $company === '') {
            return false;
        }

        return (bool) static::forCompany($company)->print_after_store_sales;
    }
}
