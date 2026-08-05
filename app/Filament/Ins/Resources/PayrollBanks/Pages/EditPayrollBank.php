<?php

namespace App\Filament\Ins\Resources\PayrollBanks\Pages;

use App\Filament\Ins\Resources\PayrollBanks\PayrollBankResource;
use App\Models\PayrollBank;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPayrollBank extends EditRecord
{
    protected static string $resource = PayrollBankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (PayrollBank $record): bool => ! $record->installmentBanks()->exists()
                    && ! $record->installmentContracts()->exists()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
