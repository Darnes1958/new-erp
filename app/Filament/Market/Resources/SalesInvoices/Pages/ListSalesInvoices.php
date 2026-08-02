<?php

namespace App\Filament\Market\Resources\SalesInvoices\Pages;

use App\Filament\Market\Pages\InpSell\InpSell;
use App\Filament\Market\Pages\InpSellOffer\InpSellOffer;
use App\Filament\Market\Pages\QuickSell\QuickSell;
use App\Filament\Market\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListSalesInvoices extends ListRecords
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('quick_sell')
                ->label('مبيعات سريعة')
                ->icon('heroicon-o-bolt')
                ->url(QuickSell::getUrl()),
            Action::make('inp_sell')
                ->label('فاتورة مبيعات جديدة')
                ->icon('heroicon-o-plus-circle')
                ->url(InpSell::getUrl()),
            Action::make('inp_sell_offer')
                ->label('فاتورة عرض جديدة')
                ->icon('heroicon-o-document-text')
                ->url(InpSellOffer::getUrl()),
        ];
    }
}
