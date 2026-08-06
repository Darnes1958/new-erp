<?php

namespace App\Filament\Finance\Resources\SalaryProfiles\Pages;

use App\Filament\Finance\Resources\SalaryProfiles\SalaryProfileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalaryProfile extends CreateRecord
{
    protected static string $resource = SalaryProfileResource::class;

    protected static ?string $title = 'ادخال مرتب';

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('create');
    }
}
