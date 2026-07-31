<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends CompanyModel
{
    public $timestamps = false;

    protected $fillable = [
        'warehouse_id',
        'item_id',
        'movement_type',
        'reference_type',
        'reference_id',
        'qty_primary',
        'qty_secondary',
        'unit_cost',
        'notes',
        'movement_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
