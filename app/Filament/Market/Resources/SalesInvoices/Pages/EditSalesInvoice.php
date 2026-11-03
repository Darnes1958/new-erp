<?php

namespace App\Filament\Market\Resources\SalesInvoices\Pages;

use App\Filament\Market\Resources\SalesInvoices\SalesInvoiceResource;
use App\Services\Inventory\SalesInvoiceCancelService;
use App\Support\Authz;
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
                ->visible(fn (): bool => Authz::can('الغاء مبيعات') && $this->record->canBeDeleted())
                ->modalHeading('إلغاء فاتورة المبيعات')
                ->modalDescription(fn (): string => $this->record->canCancelLinkedReceiptsWithInvoice() && $this->record->hasLinkedReceipts()
                    ? 'هذه فاتورة مبيعات عامة مسددة. سيتم إلغاء إيصال القبض المرتبط بها مع الفاتورة في نفس العملية.'
                    : 'هل أنت متأكد من إلغاء هذه الفاتورة؟')
                ->modalSubmitActionLabel('تأكيد الإلغاء')
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
