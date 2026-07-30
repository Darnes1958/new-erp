<?php

namespace App\Models;

class ItemPrice extends CompanyModel
{
    protected $fillable = [
        'item_id',
        'payment_method_id',
        'price_kind',
        'price_primary',
        'price_secondary',
    ];
}
