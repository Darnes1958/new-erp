<?php

namespace App\Filament\Market\Resources\CustomerReceipts\Pages;

use App\Filament\Market\Resources\CustomerReceipts\CustomerReceiptResource;
use App\Services\Payments\CustomerReceiptService;
use App\Support\ProgrammingError;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use RuntimeException;
use Throwable;

class CreateCustomerReceipt extends CreateRecord
{
    protected static string $resource = CustomerReceiptResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('create');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            return app(CustomerReceiptService::class)->prepareAttributes($data);
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
        app(CustomerReceiptService::class)->afterSaved($this->record);
    }
}
