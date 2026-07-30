<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoice extends CompanyModel
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
        'discount',
        'grand_total',
        'amount_paid',
        'balance',
        'deferred_amount',
        'refund_amount',
        'unpaid_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'is_retail' => 'boolean',
            'unpaid_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (SalesInvoice $invoice): void {
            if ($invoice->wasChanged(['amount_paid', 'extra_cost', 'discount', 'difference_amount'])) {
                $invoice->recalculateTotals();
            }
        });
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
        return $this->hasMany(SalesInvoiceLine::class);
    }

    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->lines()->sum('line_total');
        $grandTotal = $subtotal
            + (float) $this->extra_cost
            - (float) $this->discount
            + (float) $this->difference_amount;
        $paid = (float) $this->amount_paid;

        $this->forceFill([
            'lines_subtotal' => $subtotal,
            'grand_total' => $grandTotal,
            'balance' => $grandTotal - $paid,
        ])->saveQuietly();
    }
}
