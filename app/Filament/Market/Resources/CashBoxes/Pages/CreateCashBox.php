<?php

namespace App\Filament\Market\Resources\CashBoxes\Pages;

use App\Filament\Market\Resources\CashBoxes\CashBoxResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCashBox extends CreateRecord
{
    protected static string $resource = CashBoxResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('create');
    }
}
