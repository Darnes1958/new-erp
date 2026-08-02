<?php

namespace App\Filament\Market\Resources\SupplierPayments\Pages;

use App\Filament\Market\Resources\SupplierPayments\SupplierPaymentResource;
use App\Services\Payments\SupplierPaymentService;
use App\Support\ProgrammingError;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Throwable;

class EditSupplierPayment extends EditRecord
{
    protected static string $resource = SupplierPaymentResource::class;

    protected ?int $previousPurchaseInvoiceId = null;

    protected function beforeSave(): void
    {
        $this->previousPurchaseInvoiceId = $this->record->purchase_invoice_id;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

    protected function afterSave(): void
    {
        app(SupplierPaymentService::class)->afterSaved($this->record, $this->previousPurchaseInvoiceId);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => Auth::user()?->can('الغاء ايصالات موردين') || Auth::user()?->is_prog)
                ->after(function (): void {
                    app(SupplierPaymentService::class)->afterDeleted($this->record);
                }),
        ];
    }
}
