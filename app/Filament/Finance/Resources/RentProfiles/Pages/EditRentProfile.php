<?php

namespace App\Filament\Finance\Resources\RentProfiles\Pages;

use App\Filament\Finance\Resources\RentProfiles\RentProfileResource;
use App\Models\RentTransaction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRentProfile extends EditRecord
{
    protected static string $resource = RentProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (): bool => RentTransaction::query()
                    ->where('rent_profile_id', $this->record->id)
                    ->exists()),
        ];
    }
}
