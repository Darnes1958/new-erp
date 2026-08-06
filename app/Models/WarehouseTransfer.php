<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseTransfer extends CompanyModel
{
    protected $fillable = [
        'transfer_date',
        'warehouse_from_id',
        'warehouse_to_id',
        'destination_purchase_invoice_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
        ];
    }

    public function warehouseFrom(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_from_id');
    }

    public function warehouseTo(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_to_id');
    }

    public function destinationPurchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'destination_purchase_invoice_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WarehouseTransferLine::class);
    }
}
