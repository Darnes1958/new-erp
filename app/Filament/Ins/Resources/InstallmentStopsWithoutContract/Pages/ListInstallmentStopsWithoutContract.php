<?php

namespace App\Filament\Ins\Resources\InstallmentStopsWithoutContract\Pages;

use App\Filament\Ins\Pages\RecordInstallmentStopWithoutContract;
use App\Filament\Ins\Resources\InstallmentStopsWithoutContract\InstallmentStopWithoutContractResource;
use Filament\Resources\Pages\ListRecords;

class ListInstallmentStopsWithoutContract extends ListRecords
{
    protected static string $resource = InstallmentStopWithoutContractResource::class;

    public function mount(): void
    {
        $this->redirect(RecordInstallmentStopWithoutContract::getUrl(), navigate: true);
    }
}
