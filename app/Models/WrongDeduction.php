<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WrongDeduction extends CompanyModel
{
    protected $table = 'wrong_deductions';

    protected $fillable = [
        'payroll_bank_id',
        'account_number',
        'name',
        'amount',
        'status',
        'batch_id',
        'created_by',
    ];
}
