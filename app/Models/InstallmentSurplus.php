<?php

namespace App\Models;

use App\Enums\InstallmentRecordStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
            'status' => InstallmentRecordStatus::class,
        ];
    }

    public function contractable(): MorphTo
    {
        return $this->morphTo();
    }

    public function suspendedEntries(): MorphMany
    {
        return $this->morphMany(InstallmentSuspended::class, 'contractable');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DeductionBatch::class, 'batch_id');
    }

    public function isEditable(): bool
    {
        return $this->batch_id === null
            && ($this->status === null || $this->status->isOpen());
    }
}
