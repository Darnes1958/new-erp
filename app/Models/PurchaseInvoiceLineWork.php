<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoiceLineWork extends CompanyModel
{
    protected $fillable = [
        'purchase_invoice_work_id',
        'source_purchase_invoice_line_id',
        'item_id',
        'barcode',
        'qty_primary',
        'qty_secondary',
        'unit_cost_primary',
        'line_cost_total',
        'expiry_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
        ];
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceWork::class, 'purchase_invoice_work_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
