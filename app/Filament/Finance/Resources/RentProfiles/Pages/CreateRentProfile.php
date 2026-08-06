<?php

namespace App\Filament\Finance\Resources\RentProfiles\Pages;

use App\Filament\Finance\Resources\RentProfiles\RentProfileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRentProfile extends CreateRecord
{
    protected static string $resource = RentProfileResource::class;

    protected static ?string $title = 'اضافة ايجار';

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('create');
    }
}
