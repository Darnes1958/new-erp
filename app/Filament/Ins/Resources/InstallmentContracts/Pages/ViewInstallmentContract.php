<?php

namespace App\Filament\Ins\Resources\InstallmentContracts\Pages;

use App\Filament\Ins\Pages\EditInstallmentContract;
use App\Filament\Ins\Resources\InstallmentContracts\InstallmentContractResource;
use App\Filament\Ins\Support\InstallmentContractCancelAfterActions;
use App\Filament\Ins\Support\InstallmentContractDeleteActions;
use App\Support\CompanySettings;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewInstallmentContract extends ViewRecord
{
    protected static string $resource = InstallmentContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('تعديل')
                ->icon('heroicon-m-pencil')
                ->color('info')
                ->visible(fn (): bool => InstallmentContractResource::canEdit($this->record))
                ->url(fn (): string => CompanySettings::linkSalesToInstallments()
                    ? EditInstallmentContract::getUrl(['record' => $this->record->getKey()])
                    : InstallmentContractResource::getUrl('edit', ['record' => $this->record])),
            ...InstallmentContractCancelAfterActions::make(
                visible: fn (): bool => InstallmentContractResource::canDelete($this->record),
                after: fn ($record, mixed $livewire) => $livewire->redirect(
                    InstallmentContractResource::getUrl('index')
                ),
            ),
            InstallmentContractDeleteActions::make(
                visible: fn (): bool => InstallmentContractResource::canDelete($this->record),
                afterDelete: fn ($record, mixed $livewire) => $livewire->redirect(
                    InstallmentContractResource::getUrl('index')
                ),
            ),
        ];
    }
}
