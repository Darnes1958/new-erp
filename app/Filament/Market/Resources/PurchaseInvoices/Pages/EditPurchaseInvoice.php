<?php

namespace App\Filament\Market\Resources\PurchaseInvoices\Pages;

use App\Filament\Market\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    public function mount(int|string $record): void
    {
        $this->redirect(EditBuy::getUrl(['record' => $record]));
    }
}
