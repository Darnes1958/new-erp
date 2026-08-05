<?php

namespace App\Filament\Ins\Resources\BankMains\Pages;

use App\Filament\Ins\Resources\BankMains\BankMainResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBankMain extends CreateRecord
{
    protected static string $resource = BankMainResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
