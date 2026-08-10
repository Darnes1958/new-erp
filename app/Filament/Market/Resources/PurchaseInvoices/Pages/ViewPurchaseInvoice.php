<?php

namespace App\Filament\Market\Resources\PurchaseInvoices\Pages;

use App\Filament\Market\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseInvoice extends ViewRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing([
            'supplier',
            'paymentMethod',
            'warehouse',
            'lines.item',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('طباعة')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('pdf.purchase-invoice', ['purchaseInvoice' => $this->getRecord()]))
                ->openUrlInNewTab(),
            Action::make('print_item_prices')
                ->label('طباعة اسعار الأصناف')
                ->icon('heroicon-s-printer')
                ->url(fn (): string => route('pdf.purchase-invoice-item-prices', ['purchaseInvoice' => $this->getRecord()]))
                ->openUrlInNewTab(),
        ];
    }
}
