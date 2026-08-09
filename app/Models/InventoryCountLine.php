<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountLine extends CompanyModel
{
    protected $fillable = [
        'inventory_count_session_id',
        'warehouse_id',
        'item_id',
        'book_balance',
        'actual_balance',
        'quantity_difference',
        'value_amount',
        'fifo_purchase_invoice_line_id',
        'fifo_layer_created',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fifo_layer_created' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InventoryCountSession::class, 'inventory_count_session_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function fifoPurchaseInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceLine::class, 'fifo_purchase_invoice_line_id');
    }
}
