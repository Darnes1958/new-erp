<?php

namespace App\Services\Pdf;

use App\Enums\ReceiptListKind;
use App\Models\CustomerReceipt;
use App\Models\OurCompany;
use App\Models\SupplierPayment;
use App\Services\Market\PaymentReceiptVoucherService;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class PaymentReceiptVoucherPdfService
{
    public function voucher(
        Model $record,
        ReceiptListKind $kind,
        ?OurCompany $company = null,
    ): PdfBuilder {
        $voucher = app(PaymentReceiptVoucherService::class)->build($record, $kind);

        $fileName = match ($kind) {
            ReceiptListKind::Customer => 'customer-receipt-'.$record->id.'.pdf',
            ReceiptListKind::Supplier => 'supplier-payment-'.$record->id.'.pdf',
        };

        return Pdf::view('pdf.market.payment-receipt-voucher', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'voucher' => $voucher,
        ])->name($fileName);
    }

    public function customerReceipt(CustomerReceipt $record): PdfBuilder
    {
        return $this->voucher($record, ReceiptListKind::Customer);
    }

    public function supplierPayment(SupplierPayment $record): PdfBuilder
    {
        return $this->voucher($record, ReceiptListKind::Supplier);
    }
}
