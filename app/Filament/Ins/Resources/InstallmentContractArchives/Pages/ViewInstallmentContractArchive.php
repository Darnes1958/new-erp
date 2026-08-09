<?php

namespace App\Filament\Ins\Resources\InstallmentContractArchives\Pages;

use App\Filament\Ins\Resources\InstallmentContractArchives\InstallmentContractArchiveResource;
use App\Filament\Ins\Resources\InstallmentContracts\InstallmentContractResource;
use App\Models\InstallmentDeductionArchive;
use App\Services\Installments\InstallmentContractArchiveService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ViewInstallmentContractArchive extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = InstallmentContractArchiveResource::class;

    protected string $view = 'filament.ins.pages.view-installment-contract-archive';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('restore')
                ->label('استرجاع من الأرشيف')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => Auth::user()?->is_prog || Auth::user()?->can('تقرير عن عقد من الارشيف'))
                ->action(function (): void {
                    try {
                        $contract = app(InstallmentContractArchiveService::class)
                            ->restoreToActive($this->getRecord());

                        Notification::make()
                            ->title('تم استرجاع العقد بنجاح')
                            ->success()
                            ->send();

                        $this->redirect(InstallmentContractResource::getUrl('view', ['record' => $contract]));
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title(collect($exception->errors())->flatten()->first() ?? 'تعذر الاسترجاع')
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => InstallmentDeductionArchive::query()
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
                TextColumn::make('installment_due_date')
                    ->label('ت. الاستحقاق')
                    ->date('Y-m-d')
                    ->size(TextSize::ExtraSmall),
                TextColumn::make('deducted_amount')
                    ->label('الخصم')
                    ->numeric(3)
                    ->size(TextSize::ExtraSmall),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(30)
                    ->size(TextSize::ExtraSmall),
            ]);
    }
}
