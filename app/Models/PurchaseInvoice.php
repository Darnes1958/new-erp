<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoice extends CompanyModel
{
    protected $fillable = [
        'invoice_date',
        'supplier_id',
        'payment_method_id',
        'warehouse_id',
        'lines_subtotal',
        'amount_paid',
        'balance',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (PurchaseInvoice $invoice): void {
            if ($invoice->wasChanged(['amount_paid'])) {
                $invoice->recalculateTotals();
            }
        });
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
        return $this->hasMany(PurchaseInvoiceLine::class);
    }

    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->lines()->sum('line_cost_total');
        $paid = (float) $this->amount_paid;

        $this->forceFill([
            'lines_subtotal' => $subtotal,
            'balance' => $subtotal - $paid,
        ])->saveQuietly();
    }
}
