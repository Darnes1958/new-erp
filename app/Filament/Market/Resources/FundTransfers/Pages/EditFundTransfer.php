<?php

namespace App\Filament\Market\Resources\FundTransfers\Pages;

use App\Filament\Market\Resources\FundTransfers\FundTransferResource;
use App\Services\Payments\FundTransferService;
use App\Services\SystemOperationLogger;
use App\Support\SystemOperationType;
use App\Support\ProgrammingError;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Throwable;

class EditFundTransfer extends EditRecord
{
    protected static string $resource = FundTransferResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
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

    protected function afterSave(): void
    {
        SystemOperationLogger::updated(SystemOperationType::FUND_TRANSFER, $this->record->id);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => Auth::user()?->is_prog || Auth::user()?->can('الغاء تحويل'))
                ->after(function (): void {
                    SystemOperationLogger::cancelled(SystemOperationType::FUND_TRANSFER, $this->record->id);
                }),
        ];
    }
}
