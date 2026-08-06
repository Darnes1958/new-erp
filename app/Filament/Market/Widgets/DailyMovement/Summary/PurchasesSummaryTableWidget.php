<?php

namespace App\Filament\Market\Widgets\DailyMovement\Summary;

use App\Filament\Market\Widgets\DailyMovement\BaseDailyMovementTableWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PurchasesSummaryTableWidget extends BaseDailyMovementTableWidget
{
    public function table(Table $table): Table
    {
        $service = $this->dailyMovementService();

        return $this->configureTable($table, 'warehouse_name', 'asc')
            ->query(fn () => $service->purchasesByWarehouseSummary($this->dateFrom, $this->dateTo, $this->warehouseId))
            ->heading($this->sectionHeading('المشتريات'))
            ->columns([
                TextColumn::make('warehouse_name')
                    ->label('نقطة البيع')
                    ->color('info'),
                TextColumn::make('total_amount')
                    ->label('الإجمالي')
                    ->numeric(3),
                TextColumn::make('paid_amount')
                    ->label('المدفوع')
                    ->numeric(3),
                TextColumn::make('balance_amount')
                    ->label('الباقي')
                    ->numeric(3),
            ]);
    }
}
