<?php

namespace App\Models;

class ItemMovementEntry extends CompanyModel
{
    protected $table = 'item_movement_entries';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'row_key';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'order_date' => 'date',
        ];
    }
}
