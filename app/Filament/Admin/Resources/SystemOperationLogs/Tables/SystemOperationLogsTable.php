<?php

namespace App\Filament\Admin\Resources\SystemOperationLogs\Tables;

use App\Enums\SystemOperationAction;
use App\Models\SystemOperationLog;
use App\Support\SystemOperationType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SystemOperationLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('الوقت')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('operation')
                    ->label('العملية')
                    ->formatStateUsing(fn (string $state): string => SystemOperationType::label($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('action')
                    ->label('النوع')
                    ->badge()
                    ->sortable(),
                TextColumn::make('record_id')
                    ->label('الرقم')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('الزبون')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item_id')
                    ->label('رقم الصنف')
                    ->placeholder('—')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('item.name')
                    ->label('الصنف')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('user_id')
                    ->label('user_id')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('operation')
                    ->label('العملية')
                    ->options(SystemOperationType::labels()),
                SelectFilter::make('action')
                    ->label('النوع')
                    ->options(SystemOperationAction::class),
                SelectFilter::make('user_id')
                    ->label('المستخدم')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('customer_id')
                    ->label('الزبون')
                    ->schema([
                        Select::make('customer_id')
                            ->label('الزبون')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['customer_id'] ?? null,
                        fn (Builder $query, $customerId): Builder => $query->where('customer_id', $customerId),
                    )),
                Filter::make('item_id')
                    ->label('رقم الصنف')
                    ->schema([
                        Select::make('item_id')
                            ->label('الصنف')
                            ->relationship('item', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['item_id'] ?? null,
                        fn (Builder $query, $itemId): Builder => $query->where('item_id', $itemId),
                    )),
                Filter::make('created_at')
                    ->label('التاريخ')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('من'),
                        DatePicker::make('date_to')
                            ->label('إلى'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['date_to'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->default([
                        'date_from' => now()->toDateString(),
                    ]),
            ])
            ->striped()
            ->paginated([15, 25, 50, 100]);
    }
}
