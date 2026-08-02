<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class InstallmentSurplus extends CompanyModel
{
    protected $table = 'installment_surplus';

    protected $fillable = [
        'contractable_type',
        'contractable_id',
        'surplus_date',
        'amount',
        'status',
        'suspended_id',
        'batch_id',
        'deduction_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'surplus_date' => 'date',
        ];
    }

    public function contractable(): MorphTo
    {
        return $this->morphTo();
    }
}
