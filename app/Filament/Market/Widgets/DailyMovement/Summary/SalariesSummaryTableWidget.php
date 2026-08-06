<?php

namespace App\Filament\Market\Widgets\DailyMovement\Summary;

use App\Filament\Market\Widgets\DailyMovement\BaseDailyMovementTableWidget;
use App\Enums\SalaryTransactionType;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalariesSummaryTableWidget extends BaseDailyMovementTableWidget
{
    public function table(Table $table): Table
    {
        $service = $this->dailyMovementService();

        return $this->configureTable($table, 'transaction_type', 'asc')
            ->query(fn () => $service->salariesSummary($this->dateFrom, $this->dateTo, $this->warehouseId))
            ->heading($this->sectionHeading('المرتبات'))
            ->columns([
                TextColumn::make('transaction_type')
                    ->label('البيان')
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof SalaryTransactionType) {
                            return $state->getLabel();
                        }

                        return SalaryTransactionType::tryFrom((string) $state)?->getLabel() ?? (string) $state;
                    })
                    ->badge(),
                TextColumn::make('total_amount')
                    ->label('المبلغ')
                    ->numeric(3),
            ]);
    }
}
