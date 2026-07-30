<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentContract extends CompanyModel
{
    public $incrementing = false;

    protected $fillable = [
        'id',
        'customer_id',
        'installment_bank_id',
        'workplace_id',
        'payroll_bank_id',
        'bank_account_number',
        'contract_start',
        'contract_end',
        'contract_total',
        'installment_count',
        'installment_amount',
        'total_paid',
        'balance',
        'sales_invoice_id',
        'cheques_in',
        'cheques_out',
        'last_deduction_month',
        'next_installment_date',
        'late_amount',
        'installments_remaining',
        'surplus_count',
        'surplus_amount',
        'suspended_count',
        'suspended_amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'contract_start' => 'date',
            'contract_end' => 'date',
            'last_deduction_month' => 'date',
            'next_installment_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function installmentBank(): BelongsTo
    {
        return $this->belongsTo(InstallmentBank::class);
    }

    public function workplace(): BelongsTo
    {
        return $this->belongsTo(Workplace::class);
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(InstallmentDeduction::class);
    }
}
