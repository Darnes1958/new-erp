<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseTransferLine extends CompanyModel
{
    protected $fillable = [
        'warehouse_transfer_id',
        'item_id',
        'qty_primary',
        'qty_secondary',
        'created_by',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(WarehouseTransfer::class, 'warehouse_transfer_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function layers(): HasMany
    {
        return $this->hasMany(WarehouseTransferLayer::class);
    }
}
