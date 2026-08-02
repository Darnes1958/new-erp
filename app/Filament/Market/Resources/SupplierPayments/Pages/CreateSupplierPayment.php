<?php

namespace App\Filament\Market\Resources\SupplierPayments\Pages;

use App\Filament\Market\Resources\SupplierPayments\SupplierPaymentResource;
use App\Services\Payments\SupplierPaymentService;
use App\Support\ProgrammingError;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use RuntimeException;
use Throwable;

class CreateSupplierPayment extends CreateRecord
{
    protected static string $resource = SupplierPaymentResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('create');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            return app(SupplierPaymentService::class)->prepareAttributes($data);
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
        app(SupplierPaymentService::class)->afterSaved($this->record);
    }
}
