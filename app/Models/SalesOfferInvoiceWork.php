<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOfferInvoiceWork extends CompanyModel
{
    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'invoice_date',
        'customer_id',
        'payment_method_id',
        'warehouse_id',
        'is_retail',
        'lines_subtotal',
        'extra_cost',
        'rate_markup',
        'difference_amount',
        'grand_total',
        'notes',
        'user_id',
        'source_sales_offer_invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'is_retail' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
        return $this->hasMany(SalesOfferInvoiceLineWork::class, 'sales_offer_invoice_work_id');
    }
}
