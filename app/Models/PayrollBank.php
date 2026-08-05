<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollBank extends CompanyModel
{
    protected $fillable = [
        'name',
        'account_number',
        'bank_main_id',
    ];

    public function bankMain(): BelongsTo
    {
        return $this->belongsTo(BankMain::class);
    }

    public function installmentBanks(): HasMany
    {
        return $this->hasMany(InstallmentBank::class);
    }

    public function installmentContracts(): HasMany
    {
        return $this->hasMany(InstallmentContract::class);
    }
}
