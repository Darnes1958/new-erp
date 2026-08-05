<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentDeductionArchive extends CompanyModel
{
    protected $table = 'installment_deduction_archives';

    protected $fillable = [
        'installment_contract_id',
        'sequence',
        'deducted_amount',
        'deduction_date',
        'installment_due_date',
        'deduction_type_id',
        'notes',
        'batch_id',
        'remaining_balance',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'deduction_date' => 'date',
            'installment_due_date' => 'date',
        ];
    }

    public function installmentContractArchive(): BelongsTo
    {
        return $this->belongsTo(InstallmentContractArchive::class, 'installment_contract_id');
    }
}
