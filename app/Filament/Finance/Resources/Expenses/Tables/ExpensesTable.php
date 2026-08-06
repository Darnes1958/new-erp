<?php

namespace App\Filament\Finance\Resources\Expenses\Tables;

use App\Models\Expense;
use App\Models\Warehouse;
use Carbon\Carbon;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('expense_date', 'desc')
            ->columns([
                TextColumn::make('expense_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('expenseType.name')
                    ->label('البيان')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('bankAccount.name')
                    ->label('المصرف')
                    ->placeholder('—'),
                TextColumn::make('cashBox.name')
                    ->label('الخزينة')
                    ->placeholder('—'),
                TextColumn::make('warehouse.name')
                    ->label('المكان')
                    ->placeholder('—'),
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->numeric(3)
                    ->summarize(
                        Sum::make()
                            ->label('')
                            ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ','),
                    )
                    ->sortable(),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(40),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('مكان معين')
                    ->options(fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('expense_type_id')
                    ->label('نوع المصروفات')
                    ->relationship('expenseType', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('expense_date')
                    ->schema([
                        DatePicker::make('date_from')->label('من تاريخ'),
                        DatePicker::make('date_to')->label('إلي تاريخ'),
                    ])
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['date_from'] && ! $data['date_to']) {
                            return null;
                        }

                        if ($data['date_from'] && ! $data['date_to']) {
                            return 'من '.Carbon::parse($data['date_from'])->toFormattedDateString();
                        }

                        if (! $data['date_from'] && $data['date_to']) {
                            return 'حتى '.Carbon::parse($data['date_to'])->toFormattedDateString();
                        }

                        return 'من '.Carbon::parse($data['date_from'])->toFormattedDateString()
                            .' إلي '.Carbon::parse($data['date_to'])->toFormattedDateString();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['date_from'], fn (Builder $q, $date): Builder => $q->whereDate('expense_date', '>=', $date))
                            ->when($data['date_to'], fn (Builder $q, $date): Builder => $q->whereDate('expense_date', '<=', $date));
                    }),
            ])
            ->filtersFormWidth(Width::ExtraSmall)
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (): bool => Auth::user()?->is_prog || Auth::user()?->can('الغاء مصروفات')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('editWarehouse')
                        ->label('تعديل المكان')
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            Select::make('warehouse_id')
                                ->label('قم باختيار المكان')
                                ->options(fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->preload(),
                        ])
                        ->requiresConfirmation()
                        ->action(function (Collection $records, array $data): void {
                            if ($data['warehouse_id'] ?? null) {
                                $records->each->update(['warehouse_id' => $data['warehouse_id']]);
                            }
                        }),
                ]),
            ]);
    }
}
