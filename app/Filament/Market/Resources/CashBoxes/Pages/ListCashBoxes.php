<?php

namespace App\Filament\Market\Resources\CashBoxes\Pages;

use App\Filament\Market\Resources\CashBoxes\CashBoxResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCashBoxes extends ListRecords
{
    protected static string $resource = CashBoxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
