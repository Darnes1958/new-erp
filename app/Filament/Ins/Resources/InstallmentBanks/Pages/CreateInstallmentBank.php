<?php

namespace App\Filament\Ins\Resources\InstallmentBanks\Pages;

use App\Filament\Ins\Resources\InstallmentBanks\InstallmentBankResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInstallmentBank extends CreateRecord
{
    protected static string $resource = InstallmentBankResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
