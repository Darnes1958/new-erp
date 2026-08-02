<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollBank extends CompanyModel
{
    protected $fillable = [
        'name',
        'account_number',
    ];

    public function installmentBanks(): HasMany
    {
        return $this->hasMany(InstallmentBank::class);
    }
}
