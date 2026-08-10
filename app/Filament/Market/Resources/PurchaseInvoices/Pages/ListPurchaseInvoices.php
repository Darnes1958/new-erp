<?php

namespace App\Filament\Market\Resources\PurchaseInvoices\Pages;

use App\Filament\Market\Pages\InpBuy\InpBuy;
use App\Filament\Market\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseInvoices extends ListRecords
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->with([
            'supplier',
            'warehouse',
            'lines.item',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('inpBuy')
                ->label('فاتورة مشتريات جديدة')
                ->icon('heroicon-o-plus-circle')
                ->url(fn (): string => InpBuy::getUrl()),
        ];
    }
}