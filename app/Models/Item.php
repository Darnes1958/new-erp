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

    public static function resolveBuyPrice(int $itemId, int $paymentMethodId): float
    {
        $price = ItemPrice::query()
            ->where('item_id', $itemId)
            ->where('payment_method_id', $paymentMethodId)
            ->where('price_kind', 'buy')
            ->value('price_primary');

        if ($price !== null && (float) $price > 0) {
            return (float) $price;
        }

        $default = (float) static::query()->whereKey($itemId)->value('default_buy_price');

        return $default > 0 ? $default : 0.0;
    }

    public function buyPriceFor(int $paymentMethodId): float
    {
        return static::resolveBuyPrice($this->id, $paymentMethodId);
    }

    public function sellPriceFor(int $paymentMethodId): float
    {
        return (float) ($this->prices()
            ->where('payment_method_id', $paymentMethodId)
            ->where('price_kind', 'sell')
            ->value('price_primary') ?? 0);
    }
}
