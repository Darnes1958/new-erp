<?php

namespace App\Filament\Market\Widgets\DailyMovement\Detail;

use App\Enums\SalaryTransactionType;
use App\Filament\Market\Widgets\DailyMovement\BaseDailyMovementTableWidget;
use App\Models\SalaryTransaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalariesTableWidget extends BaseDailyMovementTableWidget
{
    public function table(Table $table): Table
    {
        $service = $this->dailyMovementService();

        return $this->configureTable($table, 'transaction_date')
            ->query(fn () => $service->salariesDetailQuery($this->dateFrom, $this->dateTo, $this->warehouseId))
            ->heading($this->sectionHeading('المرتبات'))
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('التاريخ')
                    ->date('Y-m-d'),
                TextColumn::make('salaryProfile.name')
                    ->label('الموظف')
                    ->color('primary'),
                TextColumn::make('transaction_type')
                    ->label('البيان')
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof SalaryTransactionType) {
                            return $state->getLabel();
                        }

                        return SalaryTransactionType::tryFrom((string) $state)?->getLabel() ?? (string) $state;
                    })
                    ->color(fn (SalaryTransactionType $state): string => match ($state) {
                        SalaryTransactionType::Salary => 'success',
                        SalaryTransactionType::Withdrawal => 'danger',
                        SalaryTransactionType::Deduction => 'primary',
                        SalaryTransactionType::Addition => 'info',
                    }),
                TextColumn::make('payment_source')
                    ->label('دفعت من')
                    ->state(fn (SalaryTransaction $record): string => $service->paymentSourceLabel(
                        $record->bankAccount?->name,
                        $record->cashBox?->name,
                    ))
                    ->color(fn (SalaryTransaction $record): string => $record->bank_account_id ? 'info' : 'success'),
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
