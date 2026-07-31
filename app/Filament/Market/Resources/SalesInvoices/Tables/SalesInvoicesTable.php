<?php

namespace App\Filament\Market\Resources\SalesInvoices\Tables;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalesInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('الزبون')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('invoice_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('lines_subtotal')
                    ->label('إجمالي البنود')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->label('الإجمالي')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->label('المدفوع')
                    ->numeric(decimalPlaces: 3),
                TextColumn::make('balance')
                    ->label('الباقي')
                    ->numeric(decimalPlaces: 3),
                IconColumn::make('is_retail')
                    ->label('مفرد')
                    ->boolean(),
                TextColumn::make('warehouse.name')
                    ->label('المخزن')
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->filters([
                SelectFilter::make('customer_id')
                    ->label('الزبون')
                    ->relationship('customer', 'name'),
                SelectFilter::make('warehouse_id')
                    ->label('المخزن')
                    ->relationship('warehouse', 'name'),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
                Action::make('print')
                    ->label('طباعة')
                    ->icon('heroicon-o-printer')
                    ->iconButton()
                    ->url(fn ($record): string => route('pdf.sales-invoice', ['salesInvoice' => $record]))
                    ->openUrlInNewTab(),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }
}
