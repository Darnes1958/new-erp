<?php

namespace App\Models;

use App\Enums\DeductionBatchEntryType;
use App\Enums\DeductionBatchPostedType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DeductionBatchLine extends CompanyModel
{
    protected $fillable = [
        'deduction_batch_id',
        'contractable_type',
        'contractable_id',
        'account_number',
        'amount',
        'deduction_date',
        'notes',
        'entry_type',
        'posted_type',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'deduction_date' => 'date',
            'entry_type' => DeductionBatchEntryType::class,
            'posted_type' => DeductionBatchPostedType::class,
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DeductionBatch::class, 'deduction_batch_id');
    }

    public function contractable(): MorphTo
    {
        return $this->morphTo();
    }
}
