<?php

namespace App\Filament\Ins\Resources\InstallmentContracts\Tables;

use App\Filament\Ins\Pages\EditInstallmentContract;
use App\Filament\Ins\Resources\InstallmentContracts\InstallmentContractResource;
use App\Filament\Ins\Support\InstallmentContractCancelAfterActions;
use App\Filament\Ins\Support\InstallmentContractDeleteActions;
use App\Filament\Ins\Support\InstallmentContractViewModalAction;
use App\Models\InstallmentContract;
use App\Services\Installments\InstallmentStopService;
use App\Support\CompanySettings;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class InstallmentContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('الزبون')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('installmentBank.name')
                    ->label('المصرف')
                    ->sortable(),
                TextColumn::make('contract_total')
                    ->label('قيمة العقد')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('installment_amount')
                    ->label('القسط')
                    ->numeric(3),
                TextColumn::make('balance')
                    ->label('الباقي')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('installments_remaining')
                    ->label('متبقي')
                    ->sortable(),
                TextColumn::make('next_installment_date')
                    ->label('قسط قادم')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->filters([
                SelectFilter::make('installment_bank_id')
                    ->label('المصرف')
                    ->relationship('installmentBank', 'name'),
                Filter::make('paid_off')
                    ->label('المسددة')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('balance', '<=', 0)),
            ])
            ->recordUrl(null)
            ->checkIfRecordIsSelectableUsing(fn (InstallmentContract $record): bool => (float) $record->balance <= 0)
            ->toolbarActions([
                BulkAction::make('stopAndArchive')
                    ->label('إيقاف وأرشفة')
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->deselectRecordsAfterCompletion()
                    ->visible(fn ($livewire): bool => static::canStopAndArchive() && static::isPaidOffFilterActive($livewire))
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-exclamation-triangle')
                    ->modalIconColor('warning')
                    ->modalHeading('إيقاف الخصم ونقل العقود إلى الأرشيف')
                    ->modalDescription('سيتم تخزين رسائل إيقاف جماعية للعقود المحددة، ثم نقلها إلى الأرشيف مع خصوماتها. تأكد من اختيار العقود المسددة بالكامل.')
                    ->modalSubmitActionLabel('تأكيد الإيقاف والأرشفة')
                    ->modalCancelActionLabel('تراجع')
                    ->schema([
                        DatePicker::make('stop_date')
                            ->label('تاريخ الإيقاف')
                            ->default(now()->toDateString())
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        if ($records->isEmpty()) {
                            Notification::make()
                                ->title('لم يتم اختيار أي عقد')
                                ->warning()
                                ->send();

                            return;
                        }

                        $result = app(InstallmentStopService::class)->stopAndArchiveMany(
                            $records,
                            Carbon::parse($data['stop_date'] ?? now()->toDateString()),
                        );

                        if ($result['archived'] > 0) {
                            Notification::make()
                                ->title('تم تخزين رسائل إيقاف لـ '.$result['stopped'].' عقد، ونقل '.$result['archived'].' عقد إلى الأرشيف')
                                ->success()
                                ->send();
                        }

                        if ($result['skipped_unpaid'] > 0) {
                            Notification::make()
                                ->title('تم تجاهل '.$result['skipped_unpaid'].' عقد غير مسدد بالكامل')
                                ->warning()
                                ->send();
                        }

                        if ($result['errors'] !== []) {
                            Notification::make()
                                ->title('تعذر أرشفة بعض العقود')
                                ->body(implode("\n", $result['errors']))
                                ->danger()
                                ->send();
                        }

                        if ($result['archived'] === 0 && $result['skipped_unpaid'] === 0 && $result['errors'] === []) {
                            Notification::make()
                                ->title('لا توجد عقود قابلة للإيقاف والأرشفة')
                                ->warning()
                                ->send();
                        }
                    }),
            ])
            ->recordActions([
                InstallmentContractViewModalAction::make(),
                Action::make('edit')
                    ->iconButton()
                    ->icon('heroicon-m-pencil')
                    ->color('info')
                    ->iconSize(IconSize::Small)
                    ->visible(fn (): bool => Auth::user()?->can('تعديل عقود') || Auth::user()?->is_prog)
                    ->url(fn (InstallmentContract $record): string => CompanySettings::linkSalesToInstallments()
                        ? EditInstallmentContract::getUrl(['record' => $record->getKey()])
                        : InstallmentContractResource::getUrl('edit', ['record' => $record])),
                ...InstallmentContractCancelAfterActions::make(
                    iconButton: true,
                    visible: fn (): bool => InstallmentContractCancelAfterActions::canRun(),
                ),
                InstallmentContractDeleteActions::make(
                    iconButton: true,
                    visible: fn (): bool => InstallmentContractDeleteActions::canDeleteByPermission(),
                ),
            ]);
    }

    protected static function canStopAndArchive(): bool
    {
        $user = Auth::user();

        return $user?->is_prog
            || $user?->can('تعديل عقود')
            || $user?->can('ادخال عقود');
    }

    protected static function isPaidOffFilterActive(mixed $livewire): bool
    {
        $state = null;

        if (is_object($livewire) && method_exists($livewire, 'getTableFilterState')) {
            $state = $livewire->getTableFilterState('paid_off');
        } elseif (is_object($livewire) && isset($livewire->tableFilters['paid_off'])) {
            $state = $livewire->tableFilters['paid_off'];
        }

        if (is_array($state)) {
            $value = $state['isActive'] ?? $state['value'] ?? null;

            return $value === true || $value === 1 || $value === '1';
        }

        return (bool) $state;
    }
}
