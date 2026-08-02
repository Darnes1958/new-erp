<?php

namespace App\Filament\Market\Resources\SalesOfferInvoices\Pages;

use App\Filament\Market\Pages\InpSellOffer\InpSellOffer;
use App\Filament\Market\Resources\SalesOfferInvoices\SalesOfferInvoiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListSalesOfferInvoices extends ListRecords
{
    protected static string $resource = SalesOfferInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('inp_sell_offer')
                ->label('فاتورة عرض جديدة')
                ->icon('heroicon-o-document-text')
                ->url(InpSellOffer::getUrl()),
        ];
    }
}
