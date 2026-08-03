<?php

namespace App\Models;

use App\Enums\InstallmentRecordStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WrongDeduction extends CompanyModel
{
    protected $table = 'wrong_deductions';

    protected $fillable = [
        'payroll_bank_id',
        'account_number',
        'name',
        'amount',
        'deduction_date',
        'status',
        'batch_id',
        'suspended_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'deduction_date' => 'date',
            'status' => InstallmentRecordStatus::class,
        ];
    }

    public function payrollBank(): BelongsTo
    {
        return $this->belongsTo(PayrollBank::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DeductionBatch::class, 'batch_id');
    }

    public function suspendedEntries(): MorphMany
    {
        return $this->morphMany(InstallmentSuspended::class, 'contractable');
    }
}
