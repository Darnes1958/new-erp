<?php

namespace App\Models;

use App\Enums\BankCommissionType;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankMain extends CompanyModel
{
    protected $fillable = [
        'name',
        'r_type',
        'ratio',
    ];

    protected function casts(): array
    {
        return [
            'r_type' => BankCommissionType::class,
            'ratio' => 'decimal:3',
        ];
    }

    public function payrollBanks(): HasMany
    {
        return $this->hasMany(PayrollBank::class);
    }
}
