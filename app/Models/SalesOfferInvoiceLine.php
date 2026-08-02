<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOfferInvoiceLine extends CompanyModel
{
    protected $fillable = [
        'sales_offer_invoice_id',
        'item_id',
        'barcode',
        'qty_primary',
        'qty_secondary',
        'unit_price_primary',
        'unit_price_secondary',
        'line_total',
        'created_by',
    ];

    public function salesOfferInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesOfferInvoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
