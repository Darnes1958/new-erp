<?php

namespace App\Filament\Market\Resources\WarehouseTransfers\Pages;

use App\Filament\Market\Pages\InpWarehouseTransfer\InpWarehouseTransfer;
use App\Filament\Market\Resources\WarehouseTransfers\WarehouseTransferResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListWarehouseTransfers extends ListRecords
{
    protected static string $resource = WarehouseTransferResource::class;

    protected ?string $heading = ' ';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')
                ->label('ادخال إذن نقل')
                ->icon('heroicon-o-plus-circle')
                ->url(fn (): string => InpWarehouseTransfer::getUrl()),
        ];
    }
}
