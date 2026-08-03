<?php

namespace App\Models;

use App\Enums\DeductionBatchStatus;
use App\Support\InstallmentBankScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeductionBatch extends CompanyModel
{
    protected $fillable = [
        'payroll_bank_id',
        'installment_bank_id',
        'status',
        'batch_date',
        'from_date',
        'to_date',
        'notes',
        'total_amount',
        'posted_normal_amount',
        'posted_archive_amount',
        'posted_surplus_amount',
        'posted_partial_amount',
        'wrong_amount',
        'posted_cancelled_amount',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeductionBatchStatus::class,
            'batch_date' => 'date',
            'from_date' => 'date',
            'to_date' => 'date',
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

    public function lines(): HasMany
    {
        return $this->hasMany(DeductionBatchLine::class);
    }

    public function isOpen(): bool
    {
        return $this->status === DeductionBatchStatus::Open;
    }

    public function bankDisplayName(): ?string
    {
        $this->loadMissing(['payrollBank', 'installmentBank']);

        if (InstallmentBankScope::usesPayrollSelection()) {
            return $this->payrollBank?->name ?? $this->installmentBank?->name ?? $this->resolvedBranch()?->name;
        }

        return $this->installmentBank?->name ?? $this->payrollBank?->name ?? $this->resolvedBranch()?->name;
    }

    public function branchDisplayName(): ?string
    {
        return $this->installmentBank?->name ?? $this->resolvedBranch()?->name;
    }

    public function resolvedBranch(): ?InstallmentBank
    {
        if ($this->installment_bank_id) {
            $this->loadMissing(['installmentBank']);

            return $this->installmentBank;
        }

        if (! $this->payroll_bank_id) {
            return null;
        }

        return InstallmentBankScope::branchForPayroll((int) $this->payroll_bank_id, $this->getConnectionName());
    }

    public function displayTotalAmount(): float
    {
        if ((float) $this->total_amount > 0) {
            return (float) $this->total_amount;
        }

        return (float) $this->lines()->sum('amount');
    }
}
