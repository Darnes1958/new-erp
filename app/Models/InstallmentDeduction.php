<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentDeduction extends CompanyModel
{
    protected $fillable = [
        'installment_contract_id',
        'sequence',
        'deducted_amount',
        'deduction_date',
        'installment_due_date',
        'deduction_type_id',
        'notes',
        'batch_id',
        'surplus_id',
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

    public function installmentContract(): BelongsTo
    {
        return $this->belongsTo(InstallmentContract::class);
    }
}
