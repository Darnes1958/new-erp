<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoiceLine extends CompanyModel
{
    protected $fillable = [
        'sales_invoice_id',
        'item_id',
        'barcode',
        'qty_primary',
        'qty_secondary',
        'unit_price_primary',
        'unit_price_secondary',
        'line_total',
        'profit',
        'sales_return_id',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::saved(function (SalesInvoiceLine $line): void {
            $line->salesInvoice?->recalculateTotals();
        });

        static::deleted(function (SalesInvoiceLine $line): void {
            $line->salesInvoice?->recalculateTotals();
        });
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }
}
