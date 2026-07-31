<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FifoAllocation extends CompanyModel
{
    protected $fillable = [
        'purchase_invoice_id',
        'purchase_invoice_line_id',
        'sales_invoice_id',
        'sales_invoice_line_id',
        'item_id',
        'qty_primary',
        'qty_secondary',
        'unit_cost',
    ];

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function purchaseInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceLine::class);
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function salesInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceLine::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
