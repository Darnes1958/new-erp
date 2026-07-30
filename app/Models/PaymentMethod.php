<?php

namespace App\Models;

class PaymentMethod extends CompanyModel
{
    protected $fillable = [
        'name',
        'code',
        'rate',
        'adjustment_value',
        'adjustment_direction',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
