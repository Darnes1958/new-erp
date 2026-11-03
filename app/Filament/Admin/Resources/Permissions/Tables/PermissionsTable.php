<?php

namespace App\Filament\Admin\Resources\Permissions\Tables;

use App\Models\Permission;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PermissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('اسم الصلاحية')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('for_who')
                    ->label('المجال')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Permission::forWhoOptions()[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'ins' => 'info',
                        'admin' => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label('مستخدمون')
                    ->counts('users')
                    ->sortable(),
                TextColumn::make('roles_count')
                    ->label('أدوار')
                    ->counts('roles')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('for_who')
                    ->label('المجال')
                    ->options(Permission::forWhoOptions()),
            ])
            ->defaultSort('name')
            ->striped()
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }
}
