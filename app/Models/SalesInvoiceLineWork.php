<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoiceLineWork extends CompanyModel
{
    protected $fillable = [
        'sales_invoice_work_id',
        'source_sales_invoice_line_id',
        'item_id',
        'barcode',
        'qty_primary',
        'qty_secondary',
        'unit_price_primary',
        'unit_price_secondary',
        'line_total',
        'created_by',
    ];

    public function work(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceWork::class, 'sales_invoice_work_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
