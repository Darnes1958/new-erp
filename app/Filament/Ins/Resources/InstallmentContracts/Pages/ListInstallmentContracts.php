<?php

namespace App\Filament\Ins\Resources\InstallmentContracts\Pages;

use App\Filament\Ins\Pages\CreateInstallmentContract;
use App\Filament\Ins\Pages\MergeInstallmentContract;
use App\Filament\Ins\Resources\InstallmentContracts\InstallmentContractResource;
use App\Support\CompanySettings;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListInstallmentContracts extends ListRecords
{
    protected static string $resource = InstallmentContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createContract')
                ->label('ادخال عقد')
                ->icon('heroicon-m-document-plus')
                ->color('danger')
                ->url(fn (): string => CreateInstallmentContract::getUrl())
                ->visible(fn (): bool => (Auth::user()?->can('ادخال عقود') || Auth::user()?->is_prog)
                    && CompanySettings::linkSalesToInstallments()),
            Action::make('mergeContract')
                ->label('ضم عقد')
                ->icon('heroicon-m-document-duplicate')
                ->color('warning')
                ->url(fn (): string => MergeInstallmentContract::getUrl())
                ->visible(fn (): bool => (Auth::user()?->can('ادخال عقود') || Auth::user()?->is_prog)
                    && CompanySettings::linkSalesToInstallments()),
        ];
    }
}
