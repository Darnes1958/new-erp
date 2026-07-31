<?php

namespace App\Models;

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
}
