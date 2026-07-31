<?php

namespace App\Filament\Ins\Resources\InstallmentContracts\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InstallmentContractsTable
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
                TextColumn::make('installmentBank.name')
                    ->label('البنك')
                    ->sortable(),
                TextColumn::make('contract_total')
                    ->label('قيمة العقد')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('installment_amount')
                    ->label('القسط')
                    ->numeric(decimalPlaces: 3),
                TextColumn::make('balance')
                    ->label('الباقي')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('installments_remaining')
                    ->label('متبقي')
                    ->sortable(),
                TextColumn::make('next_installment_date')
                    ->label('قسط قادم')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->filters([
                SelectFilter::make('installment_bank_id')
                    ->label('البنك')
                    ->relationship('installmentBank', 'name'),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
            ]);
    }
}
