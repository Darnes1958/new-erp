<?php

namespace App\Filament\Market\Resources\SupplierPayments\Pages;

use App\Enums\ReceiptListKind;
use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Filament\Market\Resources\Concerns\InteractsWithPaymentReceiptListExports;
use App\Filament\Market\Resources\SupplierPayments\SupplierPaymentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListSupplierPayments extends ListRecords
{
    use InteractsWithPaymentReceiptListExports;

    protected static string $resource = SupplierPaymentResource::class;

    protected function receiptListKind(): ReceiptListKind
    {
        return ReceiptListKind::Supplier;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => Auth::user()?->can('ادخال ايصالات موردين') || Auth::user()?->is_prog),
            InstallmentListPrintActions::compactPrint('print', fn () => $this->downloadReceiptListPdf()),
            InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadReceiptListExcel()),
        ];
    }
}
