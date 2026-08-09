<?php

namespace App\Filament\Ins\Resources\InstallmentContractArchives\Tables;

use App\Filament\Ins\Resources\InstallmentContractArchives\InstallmentContractArchiveResource;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InstallmentContractArchivesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('رقم العقد')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('الزبون')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('installmentBank.name')
                    ->label('المصرف')
                    ->sortable(),
                TextColumn::make('bank_account_number')
                    ->label('رقم الحساب')
                    ->searchable(),
                TextColumn::make('contract_total')
                    ->label('الإجمالي')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('total_paid')
                    ->label('المسدد')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('balance')
                    ->label('الرصيد')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('archived_at')
                    ->label('تاريخ الأرشفة')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('installment_bank_id')
                    ->label('المصرف')
                    ->relationship('installmentBank', 'name'),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
            ]);
    }
}
