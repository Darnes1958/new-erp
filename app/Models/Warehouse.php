<?php

namespace App\Models;

class Warehouse extends CompanyModel
{
    protected $fillable = [
        'name',
        'warehouse_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
