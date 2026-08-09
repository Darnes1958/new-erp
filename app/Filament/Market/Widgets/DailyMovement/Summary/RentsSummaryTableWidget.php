<?php

namespace App\Filament\Market\Widgets\DailyMovement\Summary;

use App\Filament\Market\Widgets\DailyMovement\BaseDailyMovementTableWidget;
use App\Enums\RentTransactionType;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RentsSummaryTableWidget extends BaseDailyMovementTableWidget
{
    public function table(Table $table): Table
    {
        $service = $this->dailyMovementService();

        return $this->configureTable($table, 'transaction_type', 'asc')
            ->query(fn () => $service->rentsSummary($this->dateFrom, $this->dateTo, $this->warehouseId))
            ->heading($this->sectionHeading('الإيجارات'))
            ->columns([
                TextColumn::make('transaction_type')
                    ->label('البيان')
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof RentTransactionType) {
                            return $state->getLabel();
                        }

                        return RentTransactionType::tryFrom((string) $state)?->getLabel() ?? (string) $state;
                    })
                    ->badge(),
                TextColumn::make('total_amount')
                    ->label('المبلغ')
                    ->numeric(3),
            ]);
    }
}
