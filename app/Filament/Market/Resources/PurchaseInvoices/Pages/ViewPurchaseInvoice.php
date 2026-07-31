<?php

namespace App\Filament\Market\Resources\PurchaseInvoices\Pages;

use App\Filament\Market\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseInvoice extends ViewRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('طباعة')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('pdf.purchase-invoice', ['purchaseInvoice' => $this->getRecord()]))
                ->openUrlInNewTab(),
        ];
    }
}
