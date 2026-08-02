<?php

namespace App\Filament\Market\Resources\CustomerReceipts\Pages;

use App\Filament\Market\Resources\CustomerReceipts\CustomerReceiptResource;
use App\Services\Payments\CustomerReceiptService;
use App\Support\ProgrammingError;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Throwable;

class EditCustomerReceipt extends EditRecord
{
    protected static string $resource = CustomerReceiptResource::class;

    protected ?int $previousSalesInvoiceId = null;

    protected function beforeSave(): void
    {
        $this->previousSalesInvoiceId = $this->record->sales_invoice_id;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

    protected function afterSave(): void
    {
        app(CustomerReceiptService::class)->afterSaved($this->record, $this->previousSalesInvoiceId);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => Auth::user()?->can('الغاء ايصالات زبائن')
                    || Auth::user()?->can('االغاء ايصالات زبائن')
                    || Auth::user()?->is_prog)
                ->after(function (): void {
                    app(CustomerReceiptService::class)->afterDeleted($this->record);
                }),
        ];
    }
}
