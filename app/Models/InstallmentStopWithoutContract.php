<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentStopWithoutContract extends CompanyModel
{
    protected $table = 'installment_stops_without_contract';

    protected $fillable = [
        'name',
        'payroll_bank_id',
        'account_number',
        'stop_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'stop_date' => 'date',
        ];
    }

    public function payrollBank(): BelongsTo
    {
        return $this->belongsTo(PayrollBank::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
