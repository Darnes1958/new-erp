<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends CompanyModel
{
    protected $fillable = [
        'name',
        'account_number',
        'opening_balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function payrollBanks(): HasMany
    {
        return $this->hasMany(PayrollBank::class);
    }
}
