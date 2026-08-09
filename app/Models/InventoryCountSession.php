<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCountSession extends CompanyModel
{
    protected $fillable = [
        'title',
        'notes',
        'year',
        'is_active',
        'ended_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ended_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCountLine::class);
    }

    public static function activeSessionId(): ?int
    {
        return static::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->value('id');
    }
}
