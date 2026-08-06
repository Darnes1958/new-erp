<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function salaryProfiles(): HasMany
    {
        return $this->hasMany(SalaryProfile::class);
    }

    public function rentProfiles(): HasMany
    {
        return $this->hasMany(RentProfile::class);
    }
}
