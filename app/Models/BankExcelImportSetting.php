<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankExcelImportSetting extends CompanyModel
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'heading_row',
        'column_account_number',
        'column_customer_name',
        'column_amount',
        'column_deduction_date',
        'payroll_bank_id',
    ];

    protected function casts(): array
    {
        return [
            'heading_row' => 'integer',
        ];
    }

    public function payrollBank(): BelongsTo
    {
        return $this->belongsTo(PayrollBank::class);
    }
}
