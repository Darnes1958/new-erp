<?php

namespace App\Services\Market;

use App\Enums\ReceiptListKind;
use App\Models\CustomerReceipt;
use App\Models\SupplierPayment;
use App\Models\Warehouse;
use App\Support\ErpNumber;
use App\Support\Utf8Text;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PaymentReceiptListService
{
    public function __construct(
        protected DailyMovementReportService $dailyMovementReportService,
    ) {}

    /**
     * @return array{
     *     dateFrom: ?string,
     *     dateTo: ?string,
     *     partyName: ?string,
     *     warehouseName: ?string,
     *     kindFilterLabel: ?string
     * }
     */
    public function exportMeta(ListRecords $page, ReceiptListKind $kind): array
    {
        $dateFilter = $page->getTableFilterState($kind->dateFilterName()) ?? [];
        $partyId = data_get($page->getTableFilterState($kind->partyFilterName()), 'value');
        $warehouseId = data_get($page->getTableFilterState('warehouse_id'), 'value');
        $partyModel = $kind->partyModelClass();

        return [
            'dateFrom' => $dateFilter['date_from'] ?? null,
            'dateTo' => $dateFilter['date_to'] ?? null,
            'partyName' => filled($partyId)
                ? $partyModel::query()->whereKey($partyId)->value('name')
                : null,
            'warehouseName' => filled($warehouseId)
                ? Warehouse::query()->whereKey($warehouseId)->value('name')
                : null,
            'kindFilterLabel' => $this->kindFilterLabel($page, $kind),
        ];
    }

    /** @return list<string> */
    public function headers(ReceiptListKind $kind): array
    {
        return [
            'الرقم',
            'التاريخ',
            $kind->partyLabel(),
            'طريقة الدفع',
            'بواسطة',
            'البيان',
            'المخزن',
            'المبلغ',
            'ملاحظات',
        ];
    }

    /**
     * @param  Collection<int, CustomerReceipt|SupplierPayment>  $rows
     * @return Collection<int, list<string>>
     */
    public function displayRows(Collection $rows, ReceiptListKind $kind): Collection
    {
        return $rows->map(fn (Model $row): array => $this->displayRow($row, $kind));
    }

    /**
     * @param  Collection<int, CustomerReceipt|SupplierPayment>  $rows
     * @return list<string>
     */
    public function totalsRow(Collection $rows): array
    {
        $total = round((float) $rows->sum(fn (Model $row): float => (float) $row->amount), 3);

        return [
            '',
            '',
            '',
            '',
            '',
            '',
            'الإجمالي',
            ErpNumber::money($total),
            '',
        ];
    }

    /** @return array<int, string|float|null> */
    public function mapExcelRow(Model $row, ReceiptListKind $kind): array
    {
        return [
            (string) $row->id,
            $this->transactionDate($row, $kind)?->format('Y-m-d'),
            Utf8Text::clean($this->partyName($row, $kind)),
            Utf8Text::clean($row->paymentMethod?->name),
            Utf8Text::clean($this->paymentSourceLabel($row)),
            Utf8Text::clean($row->transaction_kind?->getLabel()),
            Utf8Text::clean($row->warehouse?->name),
            (float) $row->amount,
            Utf8Text::clean($row->notes),
        ];
    }

    protected function displayRow(Model $row, ReceiptListKind $kind): array
    {
        return [
            (string) $row->id,
            $this->transactionDate($row, $kind)?->format('Y-m-d') ?? '—',
            Utf8Text::clean($this->partyName($row, $kind)),
            Utf8Text::clean($row->paymentMethod?->name),
            Utf8Text::clean($this->paymentSourceLabel($row)),
            Utf8Text::clean($row->transaction_kind?->getLabel()),
            Utf8Text::clean($row->warehouse?->name),
            ErpNumber::money($row->amount),
            Utf8Text::clean($row->notes),
        ];
    }

    protected function partyName(Model $row, ReceiptListKind $kind): ?string
    {
        return match ($kind) {
            ReceiptListKind::Customer => $row instanceof CustomerReceipt ? $row->customer?->name : null,
            ReceiptListKind::Supplier => $row instanceof SupplierPayment ? $row->supplier?->name : null,
        };
    }

    protected function transactionDate(Model $row, ReceiptListKind $kind): ?\Illuminate\Support\Carbon
    {
        return match ($kind) {
            ReceiptListKind::Customer => $row instanceof CustomerReceipt ? $row->receipt_date : null,
            ReceiptListKind::Supplier => $row instanceof SupplierPayment ? $row->payment_date : null,
        };
    }

    protected function paymentSourceLabel(Model $row): string
    {
        return $this->dailyMovementReportService->paymentSourceLabel(
            $row->bankAccount?->name,
            $row->cashBox?->name,
        );
    }

    protected function kindFilterLabel(ListRecords $page, ReceiptListKind $kind): ?string
    {
        if (data_get($page->getTableFilterState($kind->invoiceFilterName()), 'isActive')) {
            return 'إيصالات فاتورة';
        }

        if (data_get($page->getTableFilterState('collections'), 'isActive')) {
            return 'إيصالات قبض';
        }

        if (data_get($page->getTableFilterState('payments'), 'isActive')) {
            return 'إيصالات دفع';
        }

        return null;
    }
}
