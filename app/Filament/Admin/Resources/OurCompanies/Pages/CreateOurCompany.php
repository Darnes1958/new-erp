<?php

namespace App\Filament\Admin\Resources\OurCompanies\Pages;

use App\Filament\Admin\Resources\OurCompanies\OurCompanyResource;
use App\Filament\Admin\Resources\OurCompanies\Support\OurCompanySettingsSync;
use Filament\Resources\Pages\CreateRecord;

class CreateOurCompany extends CreateRecord
{
    protected static string $resource = OurCompanyResource::class;

    protected function afterCreate(): void
    {
        OurCompanySettingsSync::syncFromFormData(
            $this->record->connection_name,
            $this->form->getState(),
        );
    }
}
