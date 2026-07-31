<?php

namespace App\Filament\Market\Resources\SalesInvoices\Pages;

use App\Filament\Market\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesInvoice extends ViewRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('طباعة')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('pdf.sales-invoice', ['salesInvoice' => $this->getRecord()]))
                ->openUrlInNewTab(),
        ];
    }
}
