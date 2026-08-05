<?php

namespace App\Filament\Ins\Resources\PayrollBanks\Pages;

use App\Filament\Ins\Resources\PayrollBanks\PayrollBankResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollBank extends CreateRecord
{
    protected static string $resource = PayrollBankResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
