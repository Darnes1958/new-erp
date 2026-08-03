<?php

namespace App\Filament\Ins\Resources\InstallmentReturns\Tables;

use App\Enums\InstallmentReturnType;
use App\Models\InstallmentCancelledDeduction;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\InstallmentSuspended;
use App\Models\InstallmentSurplus;
use App\Models\WrongDeduction;
use App\Services\Installments\InstallmentReturnService;
use App\Support\Filament\TableSummaries;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InstallmentReturnsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with([
                    'installmentContract.customer',
                    'cancelledContract.customer',
                    'contractable' => function (MorphTo $morphTo): void {
                        $morphTo->morphWith([
                            InstallmentSurplus::class => ['contractable.customer'],
                            InstallmentContract::class => ['customer'],
                            InstallmentContractArchive::class => ['customer'],
                            InstallmentCancelledDeduction::class => ['installmentContract.customer'],
                        ]);
                    },
                ]))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable(),
                TextColumn::make('installment_contract_id')
                    ->label('رقم العقد')
                    ->getStateUsing(fn (InstallmentSuspended $record): ?string => ($id = $record->displayContractId()) !== null ? (string) $id : null)
                    ->sortable(),
                TextColumn::make('installmentContract.customer.name')
                    ->label('الاسم')
                    ->getStateUsing(fn (InstallmentSuspended $record): string => self::customerLabel($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            $query->whereHas(
                                'installmentContract.customer',
                                fn (Builder $customer) => $customer->where('name', 'like', "%{$search}%")
                            )->orWhereHas(
                                'cancelledContract.customer',
                                fn (Builder $customer) => $customer->where('name', 'like', "%{$search}%")
                            )->orWhereHas(
                                'contractable',
                                fn (Builder $contractable) => $contractable
                                    ->where('name', 'like', "%{$search}%")
                                    ->where('contractable_type', 'wrong_deduction')
                            );
                        });
                    }),
                TextColumn::make('suspended_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->numeric(3)
                    ->summarize(TableSummaries::sum()),
                TextColumn::make('status')
                    ->label('البيان')
                    ->badge(),
                TextColumn::make('batch_id')
                    ->label('رقم الحافظة')
                    ->toggleable(),
            ])
            ->recordUrl(null)
            ->recordActions([
                Action::make('undoReturn')
                    ->label('إلغاء الترجيع')
                    ->icon('heroicon-o-trash')
                    ->iconButton()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('إلغاء الترجيع')
                    ->action(fn (InstallmentSuspended $record) => app(InstallmentReturnService::class)->undoReturn($record)),
            ])
            ->toolbarActions([
                BulkAction::make('undoReturn')
                    ->label('إلغاء الترجيع')
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $service = app(InstallmentReturnService::class);

                        foreach ($records as $record) {
                            $service->undoReturn($record);
                        }
                    }),
            ]);
    }

    protected static function customerLabel(InstallmentSuspended $record): string
    {
        if ($record->status === InstallmentReturnType::FromCancelled) {
            return $record->cancelledContract?->customer?->name
                ?? $record->contractable?->installmentContract?->customer?->name
                ?? '—';
        }

        if ($record->installmentContract?->customer?->name) {
            return $record->installmentContract->customer->name;
        }

        $source = $record->contractable;

        if ($source instanceof InstallmentSurplus) {
            $contract = $source->contractable;

            if ($contract instanceof InstallmentContract || $contract instanceof InstallmentContractArchive) {
                return $contract->customer?->name ?? '—';
            }
        }

        if ($source instanceof WrongDeduction) {
            return $source->name ?? '—';
        }

        if ($source instanceof InstallmentCancelledDeduction) {
            return $source->installmentContract?->customer?->name ?? '—';
        }

        return '—';
    }
}
