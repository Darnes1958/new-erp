<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentContractArchive extends CompanyModel
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
        'archived_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'contract_start' => 'date',
            'contract_end' => 'date',
            'archived_at' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }
}
