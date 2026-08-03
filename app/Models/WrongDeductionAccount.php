<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WrongDeductionAccount extends CompanyModel
{
    protected $fillable = [
        'payroll_bank_id',
        'installment_bank_id',
        'account_number',
        'name',
        'created_by',
    ];

    public function payrollBank(): BelongsTo
    {
        return $this->belongsTo(PayrollBank::class);
    }

    public function installmentBank(): BelongsTo
    {
        return $this->belongsTo(InstallmentBank::class);
    }
}
