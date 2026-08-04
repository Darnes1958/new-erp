<?php

namespace App\Filament\Ins\Resources\WrongDeductions\Tables;

use App\Enums\InstallmentRecordStatus;
use App\Models\InstallmentContract;
use App\Models\WrongDeduction;
use App\Services\Installments\WrongDeductionService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class WrongDeductionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('payrollBank'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('payrollBank.name')
                    ->label('المصرف')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('account_number')
                    ->label('رقم الحساب')
                    ->copyable()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('deduction_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->numeric(3),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('payroll_bank_id')
                    ->relationship('payrollBank', 'name')
                    ->label('مصارف'),
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(InstallmentRecordStatus::class),
                Filter::make('deduction_date')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('deduction_date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('deduction_date', '<=', $date),
                            );
                    }),
            ])
            ->recordUrl(null)
            ->recordActions([
                Action::make('correct')
                    ->label('تصحيح')
                    ->icon('heroicon-o-check')
                    ->iconButton()
                    ->color('success')
                    ->visible(fn (WrongDeduction $record): bool => $record->status?->isOpen() ?? false)
                    ->schema([
                        Select::make('installment_contract_id')
                            ->label('العقد')
                            ->options(fn (WrongDeduction $record): array => self::contractOptions($record))
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->action(function (WrongDeduction $record, array $data): void {
                        $contract = InstallmentContract::query()->findOrFail($data['installment_contract_id']);
                        app(WrongDeductionService::class)->correctToContract($record, $contract);
                    }),
                Action::make('archive')
                    ->label('أرشفة')
                    ->icon(Heroicon::ArchiveBox)
                    ->iconButton()
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (WrongDeduction $record): bool => ! ($record->status?->isOpen() ?? true))
                    ->action(fn (WrongDeduction $record) => app(WrongDeductionService::class)->archiveMany(collect([$record]))),
            ])
            ->toolbarActions([
                BulkAction::make('return')
                    ->label('ترجيع')
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => app(WrongDeductionService::class)->returnMany($records)),
                BulkAction::make('archive')
                    ->label('أرشفة')
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => app(WrongDeductionService::class)->archiveMany($records)),
                BulkAction::make('deleteOpen')
                    ->label('إلغاء')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => app(WrongDeductionService::class)->deleteOpen($records)),
            ]);
    }

    /**
     * @return array<int|string, string>
     */
    protected static function contractOptions(WrongDeduction $record): array
    {
        $query = InstallmentContract::query()
            ->with('customer')
            ->when(
                $record->payroll_bank_id,
                fn (Builder $query) => $query->where('payroll_bank_id', $record->payroll_bank_id),
            );

        return $query
            ->limit(200)
            ->get()
            ->mapWithKeys(fn (InstallmentContract $contract): array => [
                $contract->id => ($contract->customer?->name ?? '—')." — {$contract->id}",
            ])
            ->all();
    }
}
