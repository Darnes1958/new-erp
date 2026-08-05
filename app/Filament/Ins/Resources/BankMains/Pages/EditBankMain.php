<?php

namespace App\Filament\Ins\Resources\BankMains\Pages;

use App\Filament\Ins\Resources\BankMains\BankMainResource;
use App\Models\BankMain;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBankMain extends EditRecord
{
    protected static string $resource = BankMainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (BankMain $record): bool => ! $record->payrollBanks()->exists()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
