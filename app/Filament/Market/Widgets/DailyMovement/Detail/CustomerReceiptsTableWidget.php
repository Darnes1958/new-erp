<?php

namespace App\Filament\Market\Widgets\DailyMovement\Detail;

use App\Filament\Market\Widgets\DailyMovement\BaseDailyMovementTableWidget;
use App\Models\CustomerReceipt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerReceiptsTableWidget extends BaseDailyMovementTableWidget
{
    public function table(Table $table): Table
    {
        $service = $this->dailyMovementService();

        return $this->configureTable($table, 'receipt_date')
            ->query(fn () => $service->customerReceiptsDetailQuery($this->dateFrom, $this->dateTo, $this->warehouseId))
            ->heading($this->sectionHeading('إيصالات الزبائن', 'text-danger-600'))
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم الآلي'),
                TextColumn::make('receipt_date')
                    ->label('التاريخ')
                    ->date('Y-m-d'),
                TextColumn::make('customer.name')
                    ->label('الزبون'),
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
                    ->state(fn (CustomerReceipt $record): string => $service->paymentSourceLabel(
                        $record->bankAccount?->name,
                        $record->cashBox?->name,
                    )),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(40),
            ]);
    }
}
