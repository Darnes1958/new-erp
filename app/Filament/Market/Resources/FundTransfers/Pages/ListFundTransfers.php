<?php

namespace App\Filament\Market\Resources\FundTransfers\Pages;

use App\Filament\Market\Resources\FundTransfers\FundTransferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFundTransfers extends ListRecords
{
    protected static string $resource = FundTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
