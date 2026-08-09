<?php

namespace App\Filament\Market\Resources\InventoryCountSessions\Pages;

use App\Filament\Market\Resources\InventoryCountSessions\InventoryCountSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryCountSession extends EditRecord
{
    protected static string $resource = InventoryCountSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
