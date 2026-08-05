<?php

namespace App\Filament\Ins\Resources\PayrollBanks\Pages;

use App\Filament\Ins\Resources\PayrollBanks\PayrollBankResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayrollBanks extends ListRecords
{
    protected static string $resource = PayrollBankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة'),
        ];
    }
}
