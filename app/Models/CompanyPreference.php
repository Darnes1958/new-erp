<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyPreference extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'company';

    protected $keyType = 'string';

    protected $fillable = [
        'company',
        'print_after_store_sales',
    ];

    public function getConnectionName(): ?string
    {
        return (string) config('erp.central_connection', config('database.default'));
    }

    protected function casts(): array
    {
        return [
            'print_after_store_sales' => 'boolean',
        ];
    }
}
