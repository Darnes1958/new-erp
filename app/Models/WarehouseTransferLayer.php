<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseTransferLayer extends CompanyModel
{
    protected $fillable = [
        'warehouse_transfer_line_id',
        'source_purchase_invoice_line_id',
        'destination_purchase_invoice_line_id',
        'qty_primary',
        'unit_cost',
    ];

    public function transferLine(): BelongsTo
    {
        return $this->belongsTo(WarehouseTransferLine::class, 'warehouse_transfer_line_id');
    }

    public function sourcePurchaseInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceLine::class, 'source_purchase_invoice_line_id');
    }

    public function destinationPurchaseInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceLine::class, 'destination_purchase_invoice_line_id');
    }
}
