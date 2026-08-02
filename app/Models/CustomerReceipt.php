<?php

namespace App\Models;

use App\Enums\ReceiptTransactionKind;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
            'transaction_kind' => ReceiptTransactionKind::class,
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

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
