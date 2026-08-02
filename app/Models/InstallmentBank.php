<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentBank extends CompanyModel
{
    protected $fillable = ['name', 'payroll_bank_id'];

    public function payrollBank(): BelongsTo
    {
        return $this->belongsTo(PayrollBank::class);
    }
}
