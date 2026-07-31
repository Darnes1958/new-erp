<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OurCompany extends Model
{
    protected $table = 'our_companies';

    protected $fillable = [
        'connection_name',
        'display_name',
        'address',
        'phone',
        'logo_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function forCurrentUser(): ?self
    {
        $connectionName = Auth::user()?->company;

        if (! is_string($connectionName) || $connectionName === '') {
            return null;
        }

        return static::query()
            ->where('connection_name', $connectionName)
            ->where('is_active', true)
            ->first();
    }
}
