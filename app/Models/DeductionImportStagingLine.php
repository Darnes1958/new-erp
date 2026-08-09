<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeductionImportStagingLine extends CompanyModel
{
    protected $fillable = [
        'import_session_id',
        'payroll_bank_id',
        'installment_bank_id',
        'account_number',
        'customer_name',
        'amount',
        'deduction_date',
        'row_number',
        'deduction_batch_id',
        'match_status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'deduction_date' => 'date',
        ];
    }

    public function payrollBank(): BelongsTo
    {
        return $this->belongsTo(PayrollBank::class);
    }

    public function installmentBank(): BelongsTo
    {
        return $this->belongsTo(InstallmentBank::class);
    }

    public function deductionBatch(): BelongsTo
    {
        return $this->belongsTo(DeductionBatch::class);
    }
}
