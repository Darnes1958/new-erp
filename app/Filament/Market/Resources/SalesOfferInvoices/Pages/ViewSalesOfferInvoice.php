<?php

namespace App\Filament\Market\Resources\SalesOfferInvoices\Pages;

use App\Filament\Market\Resources\SalesOfferInvoices\Pages\EditSellOffer;
use App\Filament\Market\Resources\SalesOfferInvoices\SalesOfferInvoiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesOfferInvoice extends ViewRecord
{
    protected static string $resource = SalesOfferInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('تعديل')
                ->icon('heroicon-m-pencil')
                ->url(fn (): string => EditSellOffer::getUrl(['record' => $this->getRecord()])),
            Action::make('print')
                ->label('طباعة')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('pdf.sales-offer-invoice', ['salesOfferInvoice' => $this->getRecord()]))
                ->openUrlInNewTab(),
        ];
    }
}
