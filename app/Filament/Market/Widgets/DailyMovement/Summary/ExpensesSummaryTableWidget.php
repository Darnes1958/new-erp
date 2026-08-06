<?php

namespace App\Filament\Market\Widgets\DailyMovement\Summary;

use App\Filament\Market\Widgets\DailyMovement\BaseDailyMovementTableWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpensesSummaryTableWidget extends BaseDailyMovementTableWidget
{
    public function table(Table $table): Table
    {
        $service = $this->dailyMovementService();

        return $this->configureTable($table, 'expense_type_name', 'asc')
            ->query(fn () => $service->expensesSummary($this->dateFrom, $this->dateTo, $this->warehouseId))
            ->heading($this->sectionHeading('المصروفات'))
            ->columns([
                TextColumn::make('expense_type_name')
                    ->label('نوع المصروف')
                    ->color('info'),
                TextColumn::make('payment_source_name')
                    ->label('دفعت من'),
                TextColumn::make('total_amount')
                    ->label('المبلغ')
                    ->numeric(3),
            ]);
    }
}
