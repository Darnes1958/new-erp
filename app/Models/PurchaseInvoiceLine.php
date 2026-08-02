<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoiceLine extends CompanyModel
{
    protected $fillable = [
        'purchase_invoice_id',
        'item_id',
        'barcode',
        'qty_primary',
        'qty_secondary',
        'unit_cost_primary',
        'line_cost_total',
        'remaining_qty_primary',
        'remaining_qty_secondary',
        'purchase_return_id',
        'expiry_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (PurchaseInvoiceLine $line): void {
            $line->purchaseInvoice?->recalculateTotals();
        });

        static::deleted(function (PurchaseInvoiceLine $line): void {
            $line->purchaseInvoice?->recalculateTotals();
        });
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }
}
