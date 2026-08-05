<?php

namespace App\Filament\Ins\Resources\BankMains\Pages;

use App\Filament\Ins\Resources\BankMains\BankMainResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBankMains extends ListRecords
{
    protected static string $resource = BankMainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة'),
        ];
    }
}
