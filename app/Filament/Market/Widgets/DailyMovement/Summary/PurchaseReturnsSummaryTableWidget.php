<?php

namespace App\Filament\Market\Widgets\DailyMovement\Summary;

use App\Filament\Market\Widgets\DailyMovement\BaseDailyMovementTableWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PurchaseReturnsSummaryTableWidget extends BaseDailyMovementTableWidget
{
    public function table(Table $table): Table
    {
        $service = $this->dailyMovementService();

        return $this->configureTable($table, 'return_date')
            ->query(fn () => $service->purchaseReturnsByDateSummary($this->dateFrom, $this->dateTo, $this->warehouseId))
            ->heading($this->sectionHeading('ترجيع مشتريات'))
            ->columns([
                TextColumn::make('return_date')
                    ->label('التاريخ')
                    ->date('Y-m-d'),
                TextColumn::make('total_amount')
                    ->label('الإجمالي')
                    ->numeric(3),
            ]);
    }
}
