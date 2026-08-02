<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class InstallmentSuspended extends CompanyModel
{
    protected $table = 'installment_suspended';

    protected $fillable = [
        'contractable_type',
        'contractable_id',
        'suspended_date',
        'amount',
        'status',
        'batch_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'suspended_date' => 'date',
        ];
    }

    public function contractable(): MorphTo
    {
        return $this->morphTo();
    }
}
