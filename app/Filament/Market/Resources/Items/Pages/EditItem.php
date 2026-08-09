<?php

namespace App\Filament\Market\Resources\Items\Pages;

use App\Filament\Market\Resources\Items\ItemResource;
use App\Services\SystemOperationLogger;
use App\Support\SystemOperationContext;
use App\Support\SystemOperationType;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditItem extends EditRecord
{
    protected static string $resource = ItemResource::class;

    protected function afterSave(): void
    {
        SystemOperationLogger::updated(
            SystemOperationType::ITEM,
            $this->record->id,
            SystemOperationContext::item((int) $this->record->id),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
