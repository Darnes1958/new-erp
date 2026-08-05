<?php

namespace App\Filament\Ins\Resources\InstallmentBanks\Pages;

use App\Filament\Ins\Resources\InstallmentBanks\InstallmentBankResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstallmentBanks extends ListRecords
{
    protected static string $resource = InstallmentBankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة'),
        ];
    }
}
