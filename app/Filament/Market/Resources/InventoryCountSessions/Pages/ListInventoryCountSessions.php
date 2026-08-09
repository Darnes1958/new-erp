<?php

namespace App\Filament\Market\Resources\InventoryCountSessions\Pages;

use App\Filament\Market\Resources\InventoryCountSessions\InventoryCountSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryCountSessions extends ListRecords
{
    protected static string $resource = InventoryCountSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
