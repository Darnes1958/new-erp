<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoiceWork extends CompanyModel
{
    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'invoice_date',
        'supplier_id',
        'payment_method_id',
        'warehouse_id',
        'lines_subtotal',
        'discount',
        'amount_paid',
        'balance',
        'notes',
        'user_id',
        'source_purchase_invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceLineWork::class, 'purchase_invoice_work_id');
    }
}
