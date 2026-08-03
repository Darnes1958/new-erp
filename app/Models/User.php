<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'company', 'warehouse_id', 'status', 'is_prog'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Users live on the central auth database, not the active company connection.
     */
    public function getConnectionName(): ?string
    {
        return config('database.default');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ((int) $this->status === 0) {
            return false;
        }

        if ($panel->getId() === 'admin') {
            return (bool) $this->is_prog || $this->hasRole('admin');
        }

        return filled($this->company);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_prog' => 'boolean',
        ];
    }
}
