<?php

namespace App\Models;

class CashBox extends CompanyModel
{
    protected $fillable = [
        'name',
        'opening_balance',
        'assigned_user_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
