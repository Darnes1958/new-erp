<?php

namespace App\Models;

class CustomerReceipt extends CompanyModel
{
    protected $fillable = [
        'receipt_date',
        'customer_id',
        'sales_invoice_id',
        'payment_method_id',
        'transaction_kind',
        'flow_direction',
        'amount',
        'notes',
        'sequence_no',
        'cash_box_id',
        'bank_account_id',
        'warehouse_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
        ];
    }
}
