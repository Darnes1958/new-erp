<?php

namespace App\Filament\Ins\Resources\InstallmentBanks\Tables;

use App\Models\InstallmentBank;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InstallmentBanksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('اسم المصرف')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('payrollBank.name')
                    ->label('المصرف التجميعي')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('payrollBank.bankMain.name')
                    ->label('المصرف الأم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('installment_contracts_count')
                    ->label('عدد العقود')
                    ->counts('installmentContracts')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('payroll_bank_id')
                    ->label('المصرف التجميعي')
                    ->relationship('payrollBank', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('name')
            ->striped()
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (InstallmentBank $record): bool => ! $record->installmentContracts()->exists()),
            ]);
    }
}
