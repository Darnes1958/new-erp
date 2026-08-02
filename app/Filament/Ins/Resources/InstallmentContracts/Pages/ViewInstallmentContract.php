<?php

namespace App\Filament\Ins\Resources\InstallmentContracts\Pages;

use App\Filament\Ins\Pages\EditInstallmentContract;
use App\Filament\Ins\Resources\InstallmentContracts\InstallmentContractResource;
use App\Services\Installments\InstallmentContractService;
use App\Support\CompanySettings;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

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
            Action::make('cancel')
                ->label('الغاء')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => InstallmentContractResource::canDelete($this->record))
                ->action(function (): void {
                    app(InstallmentContractService::class)->cancel($this->record);

                    $this->redirect(InstallmentContractResource::getUrl('index'));
                }),
        ];
    }
}
