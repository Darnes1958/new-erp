<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemBarcode extends CompanyModel
{
    protected $fillable = [
        'item_id',
        'barcode',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
