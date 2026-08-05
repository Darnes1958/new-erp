<?php

namespace App\Filament\Admin\Resources\OurCompanies\Pages;

use App\Filament\Admin\Resources\OurCompanies\OurCompanyResource;
use App\Filament\Admin\Resources\OurCompanies\Support\OurCompanySettingsSync;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditOurCompany extends EditRecord
{
    protected static string $resource = OurCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return Auth::user()?->is_prog
            ? [DeleteAction::make()]
            : [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return OurCompanySettingsSync::mergeSettingsIntoFormData($data);
    }

    protected function afterSave(): void
    {
        OurCompanySettingsSync::syncFromFormData(
            $this->record->connection_name,
            $this->form->getState(),
        );
    }
}
