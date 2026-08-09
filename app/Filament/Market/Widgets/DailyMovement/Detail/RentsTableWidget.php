<?php

namespace App\Filament\Market\Widgets\DailyMovement\Detail;

use App\Enums\RentTransactionType;
use App\Filament\Market\Widgets\DailyMovement\BaseDailyMovementTableWidget;
use App\Models\RentTransaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RentsTableWidget extends BaseDailyMovementTableWidget
{
    public function table(Table $table): Table
    {
        $service = $this->dailyMovementService();

        return $this->configureTable($table, 'transaction_date')
            ->query(fn () => $service->rentsDetailQuery($this->dateFrom, $this->dateTo, $this->warehouseId))
            ->heading($this->sectionHeading('الإيجارات'))
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('التاريخ')
                    ->date('Y-m-d'),
                TextColumn::make('rentProfile.name')
                    ->label('الإيجار')
                    ->color('primary'),
                TextColumn::make('transaction_type')
                    ->label('البيان')
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof RentTransactionType) {
                            return $state->getLabel();
                        }

                        return RentTransactionType::tryFrom((string) $state)?->getLabel() ?? (string) $state;
                    })
                    ->color(fn (RentTransactionType $state): string => match ($state) {
                        RentTransactionType::Rent => 'success',
                        RentTransactionType::Withdrawal => 'danger',
                    }),
                TextColumn::make('payment_source')
                    ->label('دفعت من')
                    ->state(fn (RentTransaction $record): string => $service->paymentSourceLabel(
                        $record->bankAccount?->name,
                        $record->cashBox?->name,
                    ))
                    ->color(fn (RentTransaction $record): string => $record->bank_account_id ? 'info' : 'success'),
                TextColumn::make('period_month')
                    ->label('عن شهر')
                    ->formatStateUsing(fn (?string $state): string => filled($state) && $state !== '0' ? $state : '—'),
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->numeric(3),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(40),
            ]);
    }
}
