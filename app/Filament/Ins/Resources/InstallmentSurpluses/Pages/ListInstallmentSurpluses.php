<?php

namespace App\Filament\Ins\Resources\InstallmentSurpluses\Pages;

use App\Filament\Ins\Resources\InstallmentSurpluses\InstallmentSurplusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstallmentSurpluses extends ListRecords
{
    protected static string $resource = InstallmentSurplusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('إضافة'),
        ];
    }
}
