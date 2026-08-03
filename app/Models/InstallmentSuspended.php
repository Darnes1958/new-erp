<?php

namespace App\Models;

use App\Enums\InstallmentReturnType;
use App\Models\InstallmentCancelledContract;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\InstallmentSurplus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InstallmentSuspended extends CompanyModel
{
    protected $table = 'installment_suspended';

    protected $fillable = [
        'contractable_type',
        'contractable_id',
        'installment_contract_id',
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
            'status' => InstallmentReturnType::class,
        ];
    }

    public function contractable(): MorphTo
    {
        return $this->morphTo();
    }

    public function installmentContract(): BelongsTo
    {
        return $this->belongsTo(InstallmentContract::class);
    }

    public function cancelledContract(): BelongsTo
    {
        return $this->belongsTo(InstallmentCancelledContract::class, 'installment_contract_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DeductionBatch::class, 'batch_id');
    }

    public function displayContractId(): ?int
    {
        if ($this->installment_contract_id) {
            return (int) $this->installment_contract_id;
        }

        $source = $this->contractable;

        if ($source instanceof InstallmentSurplus) {
            $contract = $source->contractable;

            if ($contract instanceof InstallmentContract || $contract instanceof InstallmentContractArchive) {
                return (int) $contract->getKey();
            }
        }

        if ($source instanceof InstallmentContract || $source instanceof InstallmentContractArchive) {
            return (int) $source->getKey();
        }

        return null;
    }
}
