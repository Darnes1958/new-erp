<?php

namespace App\Filament\Ins\Resources\DeductionBatches\Tables;

use App\Enums\DeductionBatchStatus;
use App\Filament\Ins\Resources\DeductionBatches\DeductionBatchResource;
use App\Models\DeductionBatch;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DeductionBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['payrollBank', 'installmentBank']))
            ->defaultSort(fn ($query) => $query->orderBy('status')->orderByDesc('id'))
            ->columns([
                IconColumn::make('status')
                    ->label(' ')
                    ->boolean()
                    ->getStateUsing(fn (DeductionBatch $record): bool => $record->status === DeductionBatchStatus::Posted),
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('installmentBank.name')
                    ->label('المصرف')
                    ->getStateUsing(fn (DeductionBatch $record): ?string => $record->branchDisplayName())
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHas('installmentBank', fn ($b) => $b->where('name', 'like', "%{$search}%"));
                    }),
                TextColumn::make('batch_date')
                    ->label('تاريخ الحافظة')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('from_date')
                    ->label('من')
                    ->date('Y-m-d')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('to_date')
                    ->label('إلى')
                    ->date('Y-m-d')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('display_total')
                    ->label('الإجمالي')
                    ->getStateUsing(fn (DeductionBatch $record): float => $record->displayTotalAmount())
                    ->numeric(3),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
                TextColumn::make('posted_normal_amount')
                    ->label('مرحّل')
                    ->numeric(3)
                    ->toggleable(),
                TextColumn::make('posted_surplus_amount')
                    ->label('فائض')
                    ->numeric(3)
                    ->toggleable(),
                TextColumn::make('posted_partial_amount')
                    ->label('جزئي')
                    ->numeric(3)
                    ->toggleable(),
                TextColumn::make('posted_archive_amount')
                    ->label('أرشيف')
                    ->numeric(3)
                    ->toggleable(),
                TextColumn::make('wrong_amount')
                    ->label('بالخطأ')
                    ->numeric(3)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(DeductionBatchStatus::class),
            ])
            ->recordActions([
                Action::make('enterLines')
                    ->label('ادخال أقساط')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (DeductionBatch $record): string => DeductionBatchResource::getUrl('enter-lines', ['record' => $record]))
                    ->visible(fn (DeductionBatch $record): bool => $record->isOpen()),
                Action::make('viewLines')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->tooltip('عرض الأقساط')
                    ->url(fn (DeductionBatch $record): string => DeductionBatchResource::getUrl('enter-lines', ['record' => $record]))
                    ->visible(fn (DeductionBatch $record): bool => ! $record->isOpen()),
                DeleteAction::make()
                    ->visible(fn (DeductionBatch $record): bool => $record->isOpen())
                    ->using(fn (DeductionBatch $record) => app(\App\Services\Installments\DeductionBatchService::class)->deleteOpenBatch($record)),
            ])
            ->recordUrl(null);
    }
}
