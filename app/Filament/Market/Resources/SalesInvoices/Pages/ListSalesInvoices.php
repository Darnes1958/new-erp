<?php

namespace App\Filament\Market\Resources\SalesInvoices\Pages;

use App\Filament\Market\Pages\InpSell\InpSell;
use App\Filament\Market\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListSalesInvoices extends ListRecords
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('inp_sell')
                ->label('فاتورة مبيعات جديدة')
                ->icon('heroicon-o-plus-circle')
                ->url(InpSell::getUrl()),
        ];
    }
}
