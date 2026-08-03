<?php

namespace App\Filament\Ins\Resources\InstallmentCancelledContracts\Pages;

use App\Filament\Ins\Resources\InstallmentCancelledContracts\InstallmentCancelledContractResource;
use App\Models\InstallmentCancelledContract;
use App\Models\InstallmentCancelledDeduction;
use App\Services\Installments\InstallmentReturnService;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\TextSize;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ViewInstallmentCancelledContract extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = InstallmentCancelledContractResource::class;

    protected string $view = 'filament.ins.pages.view-installment-cancelled-contract';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => InstallmentCancelledDeduction::query()
                ->where('installment_contract_id', $this->record->getKey()))
            ->defaultSort('sequence')
            ->emptyStateHeading('لا توجد أقساط مخصومة')
            ->columns([
                TextColumn::make('sequence')
                    ->label('ت')
                    ->size(TextSize::ExtraSmall),
                TextColumn::make('deduction_date')
                    ->label('ت. الخصم')
                    ->date('Y-m-d')
                    ->size(TextSize::ExtraSmall),
                TextColumn::make('deducted_amount')
                    ->label('الخصم')
                    ->numeric(3)
                    ->size(TextSize::ExtraSmall),
                TextColumn::make('batch_id')
                    ->label('الحافظة')
                    ->size(TextSize::ExtraSmall)
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('return')
                    ->label('ترجيع')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->iconButton()
                    ->iconSize(IconSize::Small)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (InstallmentCancelledDeduction $record): bool => (float) $record->remaining_balance <= 0)
                    ->action(function (InstallmentCancelledDeduction $record): void {
                        app(InstallmentReturnService::class)->returnFromCancelled($record);

                        Notification::make()->title('تم ترجيع القسط')->success()->send();

                        $this->record->refresh();
                        $this->resetTable();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('return')
                    ->label('ترجيع')
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $service = app(InstallmentReturnService::class);

                        foreach ($records as $record) {
                            if ((float) $record->remaining_balance > 0) {
                                continue;
                            }

                            $service->returnFromCancelled($record);
                        }

                        Notification::make()->title('تم ترجيع الأقساط المحددة')->success()->send();

                        $this->record->refresh();
                        $this->resetTable();
                    }),
            ]);
    }
}
