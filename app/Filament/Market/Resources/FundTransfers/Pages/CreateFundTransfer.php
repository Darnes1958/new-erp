<?php

namespace App\Filament\Market\Resources\FundTransfers\Pages;

use App\Filament\Market\Resources\FundTransfers\FundTransferResource;
use App\Services\Payments\FundTransferService;
use App\Support\ProgrammingError;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use RuntimeException;
use Throwable;

class CreateFundTransfer extends CreateRecord
{
    protected static string $resource = FundTransferResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('create');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            return app(FundTransferService::class)->prepareAttributes($data);
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
}
