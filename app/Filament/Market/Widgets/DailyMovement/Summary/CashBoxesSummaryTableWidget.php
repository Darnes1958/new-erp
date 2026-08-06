<?php

namespace App\Filament\Market\Widgets\DailyMovement\Summary;

use App\Filament\Market\Widgets\DailyMovement\BaseDailyMovementTableWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CashBoxesSummaryTableWidget extends BaseDailyMovementTableWidget
{
    public function table(Table $table): Table
    {
        $service = $this->dailyMovementService();

        return $this->configureTable($table, 'cash_box_name', 'asc')
            ->query(fn () => $service->cashBoxesSummary($this->dateFrom, $this->dateTo, $this->warehouseId))
            ->heading($this->sectionHeading('أرصدة الخزائن'))
            ->columns([
                TextColumn::make('cash_box_name')
                    ->label('الخزينة')
                    ->color('info'),
                TextColumn::make('inflow_amount')
                    ->label('وارد')
                    ->numeric(3),
                TextColumn::make('outflow_amount')
                    ->label('صادر')
                    ->numeric(3),
                TextColumn::make('net_amount')
                    ->label('الصافي')
                    ->numeric(3),
            ]);
    }
}
