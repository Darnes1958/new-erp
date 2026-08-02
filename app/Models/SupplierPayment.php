<?php

namespace App\Models;

use App\Enums\ReceiptTransactionKind;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
            'transaction_kind' => ReceiptTransactionKind::class,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
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
