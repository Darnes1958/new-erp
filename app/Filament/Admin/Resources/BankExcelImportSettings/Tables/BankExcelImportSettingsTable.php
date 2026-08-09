<?php

namespace App\Filament\Admin\Resources\BankExcelImportSettings\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BankExcelImportSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الوصف')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('payrollBank.name')
                    ->label('الحساب التجميعي')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('heading_row')
                    ->label('سطر العنوان')
                    ->sortable(),
                TextColumn::make('column_amount')
                    ->label('عمود الخصم'),
                TextColumn::make('column_deduction_date')
                    ->label('عمود التاريخ'),
                TextColumn::make('column_customer_name')
                    ->label('عمود الاسم'),
                TextColumn::make('column_account_number')
                    ->label('عمود الحساب'),
            ])
            ->defaultSort('name')
            ->striped()
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }
}
