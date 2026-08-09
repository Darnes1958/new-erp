<?php

namespace App\Filament\Admin\Resources\DeductionImportStaging\Widgets;

use App\Models\DeductionImportDateRange;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class DeductionImportDateRangesWidget extends BaseWidget
{
    protected static ?string $heading = 'فترات الاستيراد السابقة';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                DeductionImportDateRange::query()
                    ->with('payrollBank')
                    ->orderByDesc('from_date'),
            )
            ->columns([
                TextColumn::make('payrollBank.name')
                    ->label('المصرف'),
                TextColumn::make('from_date')
                    ->label('من')
                    ->date('Y-m-d'),
                TextColumn::make('to_date')
                    ->label('إلى')
                    ->date('Y-m-d'),
                TextColumn::make('deduction_batch_id')
                    ->label('رقم الحافظة')
                    ->placeholder('—'),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->visible(fn (DeductionImportDateRange $record): bool => $record->deduction_batch_id === null),
            ])
            ->paginated([5, 10]);
    }
}
