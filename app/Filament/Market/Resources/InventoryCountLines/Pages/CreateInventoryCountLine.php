<?php

namespace App\Filament\Market\Resources\InventoryCountLines\Pages;

use App\Filament\Market\Resources\InventoryCountLines\InventoryCountLineResource;
use App\Services\Inventory\InventoryCountService;
use App\Support\ProgrammingError;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use RuntimeException;
use Throwable;

class CreateInventoryCountLine extends CreateRecord
{
    protected static string $resource = InventoryCountLineResource::class;

    protected ?string $heading = '';

    protected static bool $canCreateAnother = true;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('create');
    }

    protected function preserveFormDataWhenCreatingAnother(array $data): array
    {
        return [
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'inventory_count_session_id' => $data['inventory_count_session_id'] ?? null,
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            return app(InventoryCountService::class)->prepareLineAttributes($data);
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->warning()
                ->send();

            $this->halt();
        } catch (Throwable $exception) {
            ProgrammingError::notify($exception);

            $this->halt();
        }
    }

    protected function afterCreate(): void
    {
        app(InventoryCountService::class)->applyLine($this->getRecord());
    }
}
