<?php

namespace App\Filament\Ins\Resources\BankMains\Tables;

use App\Enums\BankCommissionType;
use App\Models\BankMain;
use App\Support\ErpNumber;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BankMainsTable
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
                TextColumn::make('r_type')
                    ->label('نوع العمولة')
                    ->badge()
                    ->sortable(),
                TextColumn::make('ratio')
                    ->label('القيمة / النسبة')
                    ->sortable()
                    ->formatStateUsing(function ($state, BankMain $record): string {
                        $value = ErpNumber::money($state ?? 0);

                        return $record->r_type === BankCommissionType::Percentage
                            ? "{$value} %"
                            : $value;
                    }),
                TextColumn::make('payroll_banks_count')
                    ->label('عدد الحسابات التجميعية')
                    ->counts('payrollBanks')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->striped()
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (BankMain $record): bool => ! $record->payrollBanks()->exists()),
            ]);
    }
}
