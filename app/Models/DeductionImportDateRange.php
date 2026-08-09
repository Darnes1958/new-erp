<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeductionImportDateRange extends CompanyModel
{
    protected $fillable = [
        'payroll_bank_id',
        'from_date',
        'to_date',
        'deduction_batch_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
        ];
    }

    public function payrollBank(): BelongsTo
    {
        return $this->belongsTo(PayrollBank::class);
    }

    public function deductionBatch(): BelongsTo
    {
        return $this->belongsTo(DeductionBatch::class);
    }
}
