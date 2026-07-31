<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStock extends CompanyModel
{
    protected $fillable = [
        'warehouse_id',
        'item_id',
        'quantity_primary',
        'quantity_secondary',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
