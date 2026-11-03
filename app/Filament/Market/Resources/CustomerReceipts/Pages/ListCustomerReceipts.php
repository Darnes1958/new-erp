<?php

namespace App\Filament\Market\Resources\CustomerReceipts\Pages;

use App\Enums\ReceiptListKind;
use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Filament\Market\Resources\Concerns\InteractsWithPaymentReceiptListExports;
use App\Filament\Market\Resources\CustomerReceipts\CustomerReceiptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListCustomerReceipts extends ListRecords
{
    use InteractsWithPaymentReceiptListExports;

    protected static string $resource = CustomerReceiptResource::class;

    protected function receiptListKind(): ReceiptListKind
    {
        return ReceiptListKind::Customer;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => \App\Support\Authz::can('ادخال ايصالات زبائن', 'ادخال ايصالات قبض')),
            InstallmentListPrintActions::compactPrint('print', fn () => $this->downloadReceiptListPdf()),
            InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadReceiptListExcel()),
        ];
    }
}
