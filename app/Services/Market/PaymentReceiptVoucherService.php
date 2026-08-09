<?php

namespace App\Services\Market;

use App\Enums\ReceiptListKind;
use App\Models\CustomerReceipt;
use App\Models\SupplierPayment;
use App\Support\ErpNumber;
use App\Support\Utf8Text;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class PaymentReceiptVoucherService
{
    public function __construct(
        protected DailyMovementReportService $dailyMovementReportService,
    ) {}

    /**
     * @return array{
     *     title: string,
     *     id: int,
     *     date: string,
     *     partyLine: string,
     *     amount: string,
     *     amountWords: string,
     *     transactionKind: string,
     *     paymentMethod: string,
     *     paymentSource: string,
     *     warehouse: ?string,
     *     notes: ?string
     * }
     */
    public function build(Model $record, ReceiptListKind $kind): array
    {
        $record->loadMissing($kind->eagerLoads());

        $isCollection = (int) $record->flow_direction === 0;
        $partyName = Utf8Text::clean($this->partyName($record, $kind));
        $amount = (float) $record->amount;

        return [
            'title' => $isCollection ? 'إيصال استلام' : 'إيصال صرف',
            'id' => (int) $record->id,
            'date' => $this->transactionDate($record, $kind)?->format('Y-m-d') ?? '—',
            'partyLine' => $isCollection
                ? 'استلمت من السيد : '.$partyName
                : 'صرفت للسيد : '.$partyName,
            'amount' => ErpNumber::money($amount),
            'amountWords' => $this->amountInArabicWords($amount),
            'transactionKind' => Utf8Text::clean($record->transaction_kind?->getLabel()),
            'paymentMethod' => Utf8Text::clean($record->paymentMethod?->name),
            'paymentSource' => Utf8Text::clean($this->dailyMovementReportService->paymentSourceLabel(
                $record->bankAccount?->name,
                $record->cashBox?->name,
            )),
            'warehouse' => Utf8Text::clean($record->warehouse?->name),
            'notes' => Utf8Text::clean($record->notes),
        ];
    }

    protected function partyName(Model $record, ReceiptListKind $kind): ?string
    {
        return match ($kind) {
            ReceiptListKind::Customer => $record instanceof CustomerReceipt ? $record->customer?->name : null,
            ReceiptListKind::Supplier => $record instanceof SupplierPayment ? $record->supplier?->name : null,
        };
    }

    protected function transactionDate(Model $record, ReceiptListKind $kind): ?\Illuminate\Support\Carbon
    {
        return match ($kind) {
            ReceiptListKind::Customer => $record instanceof CustomerReceipt ? $record->receipt_date : null,
            ReceiptListKind::Supplier => $record instanceof SupplierPayment ? $record->payment_date : null,
        };
    }

    protected function amountInArabicWords(float $amount): string
    {
        try {
            $words = Number::spell($amount, locale: 'ar');
        } catch (\Throwable) {
            $words = Number::spell((int) floor($amount), locale: 'ar');
        }

        return 'فقط '.$words.' دينار';
    }
}
