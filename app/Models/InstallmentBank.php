<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentBank extends CompanyModel
{
    protected $fillable = ['name', 'payroll_bank_id'];

    public function payrollBank(): BelongsTo
    {
        return $this->belongsTo(PayrollBank::class);
    }

    public function installmentContracts(): HasMany
    {
        return $this->hasMany(InstallmentContract::class);
    }
}
