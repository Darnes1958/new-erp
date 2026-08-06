<?php

namespace App\Filament\Market\Widgets\DailyMovement\Detail;

use App\Filament\Market\Widgets\DailyMovement\BaseDailyMovementTableWidget;
use App\Models\Expense;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpensesTableWidget extends BaseDailyMovementTableWidget
{
    public function table(Table $table): Table
    {
        $service = $this->dailyMovementService();

        return $this->configureTable($table, 'expense_date')
            ->query(fn () => $service->expensesDetailQuery($this->dateFrom, $this->dateTo, $this->warehouseId))
            ->heading($this->sectionHeading('المصروفات'))
            ->columns([
                TextColumn::make('expense_date')
                    ->label('التاريخ')
                    ->date('Y-m-d'),
                TextColumn::make('expenseType.name')
                    ->label('البيان')
                    ->color('info'),
                TextColumn::make('payment_source')
                    ->label('دفعت من')
                    ->state(fn (Expense $record): string => $service->paymentSourceLabel(
                        $record->bankAccount?->name,
                        $record->cashBox?->name,
                    ))
                    ->color('primary'),
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->numeric(3),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(40),
            ]);
    }
}
