<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends CompanyModel
{
    protected $fillable = [
        'name',
        'barcode',
        'item_type_id',
        'brand_id',
        'primary_unit_id',
        'secondary_unit_id',
        'has_dual_unit',
        'conversion_factor',
        'default_buy_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'has_dual_unit' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function primaryUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'primary_unit_id');
    }

    public function secondaryUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'secondary_unit_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ItemPrice::class);
    }
}
