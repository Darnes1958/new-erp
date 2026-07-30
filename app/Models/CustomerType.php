<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerType extends CompanyModel
{
    protected $fillable = ['name'];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
