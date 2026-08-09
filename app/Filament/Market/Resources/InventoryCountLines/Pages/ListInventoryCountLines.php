<?php

namespace App\Filament\Market\Resources\InventoryCountLines\Pages;

use App\Filament\Market\Resources\InventoryCountLines\InventoryCountLineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryCountLines extends ListRecords
{
    protected static string $resource = InventoryCountLineResource::class;

    protected ?string $heading = '';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
