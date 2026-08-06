<?php

namespace App\Models;

use App\Enums\RentTransactionType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentTransaction extends CompanyModel
{
    protected $fillable = [
        'rent_profile_id',
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
            'transaction_type' => RentTransactionType::class,
        ];
    }

    public function rentProfile(): BelongsTo
    {
        return $this->belongsTo(RentProfile::class);
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
