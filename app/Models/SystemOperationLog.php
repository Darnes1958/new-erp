<?php

namespace App\Models;

use App\Enums\SystemOperationAction;
use App\Support\SystemOperationType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemOperationLog extends CompanyModel
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'operation',
        'action',
        'record_id',
        'customer_id',
        'item_id',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => SystemOperationAction::class,
            'record_id' => 'integer',
            'customer_id' => 'integer',
            'item_id' => 'integer',
            'user_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operationLabel(): string
    {
        return SystemOperationType::label($this->operation);
    }
}
