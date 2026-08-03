<?php

namespace App\Filament\Ins\Resources\InstallmentCancelledContracts\Tables;

use App\Filament\Ins\Resources\InstallmentCancelledContracts\InstallmentCancelledContractResource;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InstallmentCancelledContractsTable
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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('installmentBank.name')
                    ->label('المصرف')
                    ->sortable(),
                TextColumn::make('bank_account_number')
                    ->label('رقم الحساب')
                    ->searchable(),
                TextColumn::make('contract_total')
                    ->label('قيمة العقد')
                    ->numeric(3),
                TextColumn::make('total_paid')
                    ->label('المسدد')
                    ->numeric(3),
                TextColumn::make('balance')
                    ->label('الباقي')
                    ->numeric(3),
                TextColumn::make('cancelled_at')
                    ->label('تاريخ الإلغاء')
                    ->date('Y-m-d')
                    ->sortable(),
            ])
            ->emptyStateHeading('لا توجد عقود ملغية')
            ->striped()
            ->recordActions([
                ViewAction::make()->iconButton(),
            ])
            ->recordUrl(fn ($record): string => InstallmentCancelledContractResource::getUrl('view', ['record' => $record]));
    }
}
