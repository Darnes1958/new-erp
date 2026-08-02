<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOfferInvoiceLineWork extends CompanyModel
{
    protected $fillable = [
        'sales_offer_invoice_work_id',
        'source_sales_offer_invoice_line_id',
        'item_id',
        'barcode',
        'qty_primary',
        'qty_secondary',
        'unit_price_primary',
        'unit_price_secondary',
        'line_total',
        'created_by',
    ];

    public function salesOfferInvoiceWork(): BelongsTo
    {
        return $this->belongsTo(SalesOfferInvoiceWork::class, 'sales_offer_invoice_work_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
