<?php

namespace App\Models;

use App\Enums\FinancePaymentMethod;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends CompanyModel
{
    protected $fillable = [
        'expense_type_id',
        'payment_method',
        'bank_account_id',
        'cash_box_id',
        'warehouse_id',
        'expense_date',
        'amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => FinancePaymentMethod::class,
            'expense_date' => 'date',
        ];
    }

    public function expenseType(): BelongsTo
    {
        return $this->belongsTo(ExpenseType::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
