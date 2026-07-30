<?php

namespace App\Models;

class SupplierPayment extends CompanyModel
{
    protected $fillable = [
        'payment_date',
        'supplier_id',
        'purchase_invoice_id',
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
            'payment_date' => 'date',
        ];
    }
}
