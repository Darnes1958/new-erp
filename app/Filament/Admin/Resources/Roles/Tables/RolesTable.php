<?php

namespace App\Filament\Admin\Resources\Roles\Tables;

use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('اسم الدور')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('for_who')
                    ->label('المجال')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Role::forWhoOptions()[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'ins' => 'info',
                        'admin' => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
                TextColumn::make('permissions_count')
                    ->label('صلاحيات')
                    ->counts('permissions')
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label('مستخدمون')
                    ->counts('users')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('for_who')
                    ->label('المجال')
                    ->options(Role::forWhoOptions()),
            ])
            ->defaultSort('name')
            ->striped()
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->hidden(fn (Role $record): bool => $record->name === 'admin'),
            ]);
    }
}
