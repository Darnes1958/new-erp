<?php

namespace App\Filament\Market\Widgets\DailyMovement\Summary;

use App\Filament\Market\Widgets\DailyMovement\BaseDailyMovementTableWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupplierPaymentsSummaryTableWidget extends BaseDailyMovementTableWidget
{
    public function table(Table $table): Table
    {
        $service = $this->dailyMovementService();

        return $this->configureTable($table, 'transaction_kind', 'asc')
            ->query(fn () => $service->supplierPaymentsSummary($this->dateFrom, $this->dateTo, $this->warehouseId))
            ->heading($this->sectionHeading('إيصالات الموردين'))
            ->columns([
                TextColumn::make('transaction_kind')
                    ->label('البيان')
                    ->formatStateUsing(fn ($state): string => $service->transactionKindLabel($state))
                    ->badge(),
                TextColumn::make('payment_method_name')
                    ->label('طريقة الدفع'),
                TextColumn::make('payment_source')
                    ->label('الخزينة / الحساب')
                    ->state(fn ($record): string => $service->paymentSourceLabel(
                        $record->bank_account_name ?? null,
                        $record->cash_box_name ?? null,
                    )),
                TextColumn::make('collection_amount')
                    ->label('قبض')
                    ->numeric(3),
                TextColumn::make('payment_amount')
                    ->label('دفع')
                    ->numeric(3),
            ]);
    }
}
