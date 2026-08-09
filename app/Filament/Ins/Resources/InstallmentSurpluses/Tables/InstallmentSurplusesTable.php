<?php

namespace App\Filament\Ins\Resources\InstallmentSurpluses\Tables;

use App\Enums\InstallmentRecordStatus;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\InstallmentSurplus;
use App\Services\Installments\InstallmentSurplusService;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use App\Support\Filament\TableSummaries;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class InstallmentSurplusesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['contractable.customer']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable(),
                TextColumn::make('contractable_id')
                    ->label('رقم العقد')
                    ->sortable(),
                TextColumn::make('contractable.customer.name')
                    ->label('الاسم')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHasMorph(
                            'contractable',
                            [InstallmentContract::class, InstallmentContractArchive::class],
                            fn (Builder $inner) => $inner->whereHas(
                                'customer',
                                fn (Builder $customer) => $customer->where('name', 'like', "%{$search}%")
                            )
                        );
                    }),
                TextColumn::make('surplus_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->numeric(3)
                    ->summarize(TableSummaries::sum()),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
                TextColumn::make('contractable_type')
                    ->label('حالة العقد')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'installment_contract', InstallmentContract::class => 'قائم',
                        'installment_contract_archive', InstallmentContractArchive::class => 'أرشيف',
                        default => '—',
                    }),
                TextColumn::make('batch_id')
                    ->label('حافظة')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                SelectFilter::make('contractable_type')
                    ->label('حالة العقد')
                    ->options([
                        'installment_contract' => 'قائم',
                        'installment_contract_archive' => 'أرشيف',
                    ]),
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(InstallmentRecordStatus::class),
                Filter::make('surplus_date')
                    ->label('التاريخ')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('من تاريخ'),
                        DatePicker::make('date_to')
                            ->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('surplus_date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('surplus_date', '<=', $date),
                            );
                    })
                    ->default([
                        'date_from' => now()->startOfYear()->toDateString(),
                    ]),
            ])
            ->recordUrl(null)
            ->checkIfRecordIsSelectableUsing(
                fn (Model $record): bool => $record->status?->isOpen() ?? false,
            )
            ->recordActions([
                EditAction::make()
                    ->visible(fn (InstallmentSurplus $record): bool => $record->isEditable()),
                DeleteAction::make()
                    ->visible(fn (InstallmentSurplus $record): bool => $record->isEditable())
                    ->using(fn (InstallmentSurplus $record) => app(InstallmentSurplusService::class)->delete($record)),
            ])
            ->toolbarActions([
                BulkAction::make('return')
                    ->label('ترجيع')
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        app(InstallmentSurplusService::class)->returnMany($records);
                    }),
            ]);
    }
}
