<?php

namespace App\Filament\Admin\Resources\Roles\Schemas;

use App\Models\Role;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الدور')->schema([
                TextInput::make('name')
                    ->label('اسم الدور')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (?Role $record): bool => $record?->name === 'admin'),
                Select::make('for_who')
                    ->label('المجال')
                    ->options(Role::forWhoOptions())
                    ->default('sell')
                    ->required(),
                Hidden::make('guard_name')
                    ->default('web'),
                Select::make('permissions')
                    ->label('الصلاحيات')
                    ->relationship(
                        'permissions',
                        'name',
                        fn (Builder $query): Builder => $query->orderBy('for_who')->orderBy('name'),
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }
}
