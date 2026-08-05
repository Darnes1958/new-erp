<?php

namespace App\Filament\Ins\Resources\PayrollBanks\Tables;

use App\Models\PayrollBank;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayrollBanksTable
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
                TextColumn::make('account_number')
                    ->label('رقم الحساب')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('bankMain.name')
                    ->label('المصرف الأم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('installment_banks_count')
                    ->label('عدد الفروع')
                    ->counts('installmentBanks')
                    ->sortable(),
                TextColumn::make('installment_contracts_count')
                    ->label('عدد العقود')
                    ->counts('installmentContracts')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('bank_main_id')
                    ->label('المصرف الأم')
                    ->relationship('bankMain', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('name')
            ->striped()
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (PayrollBank $record): bool => ! $record->installmentBanks()->exists()
                        && ! $record->installmentContracts()->exists()),
            ]);
    }
}
