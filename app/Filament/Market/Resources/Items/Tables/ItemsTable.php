<?php

namespace App\Filament\Market\Resources\Items\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('barcode')
                    ->label('الباركود')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('itemType.name')
                    ->label('التصنيف')
                    ->sortable(),
                TextColumn::make('brand.name')
                    ->label('الشركة')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('primaryUnit.name')
                    ->label('الوحدة')
                    ->toggleable(),
                IconColumn::make('has_dual_unit')
                    ->label('وحدتان')
                    ->boolean(),
                TextColumn::make('default_buy_price')
                    ->label('سعر الشراء')
                    ->numeric(3),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->striped()
            ->filters([
                SelectFilter::make('item_type_id')
                    ->label('التصنيف')
                    ->relationship('itemType', 'name'),
                SelectFilter::make('brand_id')
                    ->label('الشركة')
                    ->relationship('brand', 'name'),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }
}
