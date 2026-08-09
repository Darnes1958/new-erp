<?php

namespace App\Services\Pdf;

use App\Models\Item;
use App\Models\PurchaseInvoice;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class PurchaseInvoiceItemPricesPdfService
{
    public function forInvoice(PurchaseInvoice $invoice): PdfBuilder
    {
        $invoice->load(['lines.item']);

        $items = $this->itemsForInvoice($invoice);

        return Pdf::view('pdf.purchase-invoice-item-prices', [
            'items' => $items,
        ])->name("purchase-invoice-{$invoice->id}-item-prices.pdf");
    }

    /**
     * @return Collection<int, array{name: string, price: float}>
     */
    public function itemsForInvoice(PurchaseInvoice $invoice): Collection
    {
        return $invoice->lines
            ->pluck('item')
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn (Item $item): array => [
                'name' => $item->name,
                'price' => $item->sellPriceFor(1),
            ]);
    }
}
