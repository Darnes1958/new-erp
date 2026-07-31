<?php

namespace App\Filament\Market\Tables;

use App\Models\Item;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemPickerTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Item::query()->where('is_active', true))
            ->columns([
                TextColumn::make('id')
                    ->label('رقم الصنف')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('barcode')
                    ->label('الباركود')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('اسم الصنف')
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}
