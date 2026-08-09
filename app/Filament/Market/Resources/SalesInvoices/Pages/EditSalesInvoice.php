<?php

namespace App\Filament\Market\Resources\SalesInvoices\Pages;

use App\Filament\Market\Resources\SalesInvoices\SalesInvoiceResource;
use App\Services\Inventory\SalesInvoiceCancelService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSalesInvoice extends EditRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn (): bool => $this->record->canBeDeleted())
                ->before(function (): void {
                    try {
                        $this->record->assertCanBeDeleted();
                    } catch (\RuntimeException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->warning()
                            ->send();

                        throw $exception;
                    }
                })
                ->using(fn () => app(SalesInvoiceCancelService::class)->cancel($this->record)),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
