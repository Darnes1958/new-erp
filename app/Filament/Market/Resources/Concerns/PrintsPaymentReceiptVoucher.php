<?php

namespace App\Filament\Market\Resources\Concerns;

use App\Enums\ReceiptListKind;
use App\Services\Pdf\PaymentReceiptVoucherPdfService;
use App\Support\PdfDownload;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

trait PrintsPaymentReceiptVoucher
{
    abstract protected function receiptListKind(): ReceiptListKind;

    protected function printReceiptVoucherAction(): Action
    {
        return Action::make('printVoucher')
            ->label('طباعة')
            ->icon(Heroicon::OutlinedPrinter)
            ->iconButton()
            ->color('info')
            ->action(fn (Model $record) => PdfDownload::streamed(
                app(PaymentReceiptVoucherPdfService::class)->voucher(
                    $record,
                    $this->receiptListKind(),
                ),
            ));
    }

    protected function printReceiptVoucherHeaderAction(): Action
    {
        return Action::make('printVoucher')
            ->label('طباعة')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('info')
            ->action(fn () => PdfDownload::streamed(
                app(PaymentReceiptVoucherPdfService::class)->voucher(
                    $this->getRecord(),
                    $this->receiptListKind(),
                ),
            ));
    }
}
