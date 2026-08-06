<?php

namespace App\Filament\Market\Widgets\DailyMovement\Detail;

use App\Filament\Market\Widgets\DailyMovement\BaseDailyMovementTableWidget;
use App\Models\SupplierPayment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupplierPaymentsTableWidget extends BaseDailyMovementTableWidget
{
    public function table(Table $table): Table
    {
        $service = $this->dailyMovementService();

        return $this->configureTable($table, 'payment_date')
            ->query(fn () => $service->supplierPaymentsDetailQuery($this->dateFrom, $this->dateTo, $this->warehouseId))
            ->heading($this->sectionHeading('إيصالات الموردين'))
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم الآلي'),
                TextColumn::make('payment_date')
                    ->label('التاريخ')
                    ->date('Y-m-d'),
                TextColumn::make('supplier.name')
                    ->label('المورد'),
                TextColumn::make('transaction_kind')
                    ->label('البيان')
                    ->badge(),
                TextColumn::make('flow_direction')
                    ->label('النوع')
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'قبض' : 'دفع')
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'success' : 'danger'),
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->numeric(3),
                TextColumn::make('payment_source')
                    ->label('الخزينة / الحساب')
                    ->state(fn (SupplierPayment $record): string => $service->paymentSourceLabel(
                        $record->bankAccount?->name,
                        $record->cashBox?->name,
                    )),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(40),
            ]);
    }
}
