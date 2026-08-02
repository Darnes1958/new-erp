<?php

namespace App\Filament\Market\Tables;

use App\Models\Item;
use App\Support\SalesItemAvailability;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesItemPickerTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Item::query()->where('is_active', true))
            ->modifyQueryUsing(function (Builder $query) use ($table): Builder {
                $warehouseId = isset($table->getArguments()['warehouse_id'])
                    ? (int) $table->getArguments()['warehouse_id']
                    : null;

                return SalesItemAvailability::applyWarehouseStockFilter($query, $warehouseId);
            })
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
