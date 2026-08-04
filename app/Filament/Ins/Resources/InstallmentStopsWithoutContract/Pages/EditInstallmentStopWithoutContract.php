<?php

namespace App\Filament\Ins\Resources\InstallmentStopsWithoutContract\Pages;

use App\Filament\Ins\Pages\RecordInstallmentStopWithoutContract;
use App\Filament\Ins\Resources\InstallmentStopsWithoutContract\InstallmentStopWithoutContractResource;
use Filament\Resources\Pages\EditRecord;

class EditInstallmentStopWithoutContract extends EditRecord
{
    protected static string $resource = InstallmentStopWithoutContractResource::class;

    protected function getRedirectUrl(): string
    {
        return RecordInstallmentStopWithoutContract::getUrl();
    }
}
