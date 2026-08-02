<?php

namespace App\Support;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Auth;

class CompanySettings
{
    public static function current(): ?CompanySetting
    {
        $company = Auth::user()?->company;

        if (! is_string($company) || $company === '') {
            return null;
        }

        return CompanySetting::query()->find($company);
    }

    public static function multiWarehouse(): bool
    {
        return (bool) static::current()?->multi_warehouse;
    }

    public static function hasExpiryDates(): bool
    {
        return (bool) static::current()?->has_expiry_dates;
    }

    public static function barcodeEnabled(): bool
    {
        return (bool) static::current()?->barcode_enabled;
    }

    public static function hasDualUnit(): bool
    {
        return (bool) static::current()?->has_dual_unit;
    }

    public static function wholesaleRetail(): bool
    {
        return (bool) static::current()?->wholesale_retail;
    }

    public static function linkSalesToInstallments(): bool
    {
        return (bool) static::current()?->link_sales_to_installments;
    }

    /** @see InstallmentBankScope Policy: aggregative = one branch per payroll; branch = many branches per payroll */
    public static function installmentByPayrollBank(): bool
    {
        return static::current()?->installment_by_payroll_bank ?? true;
    }
}
