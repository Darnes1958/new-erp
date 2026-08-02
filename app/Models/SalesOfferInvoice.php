<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOfferInvoice extends CompanyModel
{
    protected $fillable = [
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
        'created_by',
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
        return $this->hasMany(SalesOfferInvoiceLine::class);
    }
}
