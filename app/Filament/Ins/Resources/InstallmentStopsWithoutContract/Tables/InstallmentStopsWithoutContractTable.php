<?php

namespace App\Filament\Ins\Resources\InstallmentStopsWithoutContract\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InstallmentStopsWithoutContractTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable(),
                TextColumn::make('payrollBank.name')
                    ->label('المصرف التجميعي')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('account_number')
                    ->label('رقم الحساب')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('stop_date')
                    ->label('تاريخ الإيقاف')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('المستخدم'),
                TextColumn::make('created_at')
                    ->label('تاريخ الإدخال')
                    ->dateTime('Y-m-d H:i')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('لا توجد بيانات')
            ->striped();
    }
}
