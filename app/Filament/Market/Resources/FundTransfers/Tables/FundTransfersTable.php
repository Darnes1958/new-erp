<?php

namespace App\Filament\Market\Resources\FundTransfers\Tables;

use App\Enums\FundTransferKind;
use App\Models\FundTransfer;
use App\Services\SystemOperationLogger;
use App\Support\ErpNumber;
use App\Support\SystemOperationType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class FundTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('transfer_kind')
                    ->label('البيان')
                    ->badge()
                    ->sortable(),
                TextColumn::make('transfer_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('from_account')
                    ->label('من')
                    ->state(fn (FundTransfer $record): string => $record->fromAccountName()),
                TextColumn::make('to_account')
                    ->label('إلى')
                    ->state(fn (FundTransfer $record): string => $record->toAccountName()),
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->formatStateUsing(fn ($state): string => ErpNumber::money($state))
                    ->sortable(),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('transfer_kind')
                    ->label('البيان')
                    ->options(collect(FundTransferKind::cases())
                        ->mapWithKeys(fn (FundTransferKind $kind): array => [$kind->value => $kind->getLabel()])
                        ->all()),
                Filter::make('transfer_date')
                    ->schema([
                        DatePicker::make('from_date')
                            ->label('من تاريخ'),
                        DatePicker::make('to_date')
                            ->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_date'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('transfer_date', '>=', $date),
                            )
                            ->when(
                                $data['to_date'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('transfer_date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (): bool => Auth::user()?->is_prog || Auth::user()?->can('الغاء تحويل'))
                    ->after(function (FundTransfer $record): void {
                        SystemOperationLogger::cancelled(SystemOperationType::FUND_TRANSFER, $record->id);
                    }),
            ]);
    }
}
