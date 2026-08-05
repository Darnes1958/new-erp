<?php

namespace App\Filament\Ins\Resources\InstallmentBanks\Pages;

use App\Filament\Ins\Resources\InstallmentBanks\InstallmentBankResource;
use App\Models\InstallmentBank;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInstallmentBank extends EditRecord
{
    protected static string $resource = InstallmentBankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (InstallmentBank $record): bool => ! $record->installmentContracts()->exists()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
