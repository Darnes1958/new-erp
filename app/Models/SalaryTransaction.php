<?php

namespace App\Models;

use App\Enums\SalaryTransactionType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryTransaction extends CompanyModel
{
    protected $fillable = [
        'salary_profile_id',
        'transaction_date',
        'transaction_type',
        'amount',
        'period_month',
        'bank_account_id',
        'cash_box_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'transaction_type' => SalaryTransactionType::class,
        ];
    }

    public function salaryProfile(): BelongsTo
    {
        return $this->belongsTo(SalaryProfile::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }
}
