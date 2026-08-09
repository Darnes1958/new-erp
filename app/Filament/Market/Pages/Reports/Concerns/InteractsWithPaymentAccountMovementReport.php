<?php

namespace App\Filament\Market\Pages\Reports\Concerns;

use App\Models\BankAccountLedgerEntry;
use App\Models\CashBoxLedgerEntry;
use App\Services\Excel\MarketExcelService;
use App\Services\Market\PaymentAccountLedgerReportService;
use App\Services\Pdf\PaymentAccountLedgerPdfService;
use App\Support\PdfDownload;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait InteractsWithPaymentAccountMovementReport
{
    abstract protected function accountIdProperty(): string;

    abstract protected function accountName(?int $accountId): ?string;

    abstract protected function movementQuery(PaymentAccountLedgerReportService $service): Builder;

    abstract protected function openingBalance(PaymentAccountLedgerReportService $service): float;

    /**
     * @return array{debit: float, credit: float, balance: float}
     */
    abstract protected function periodTotals(PaymentAccountLedgerReportService $service): array;

    abstract protected function reportTitle(): string;

    abstract protected function excelReportTitle(): string;

    abstract protected function validateAccountSelected(): bool;

    protected function downloadPaymentAccountExcel(): mixed
    {
        $rows = $this->exportPaymentAccountRows();

        if ($rows === null) {
            return null;
        }

        $service = app(PaymentAccountLedgerReportService::class);
        $accountId = $this->{$this->accountIdProperty()};
        $openingBalance = $this->openingBalance($service);
        $rows = $service->attachRunningBalance($rows, $openingBalance);

        return app(MarketExcelService::class)->paymentAccountMovement(
            rows: $rows,
            reportTitle: $this->excelReportTitle(),
            accountName: $this->accountName($accountId) ?? '',
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            openingBalance: $openingBalance,
            periodTotals: $this->periodTotals($service),
        );
    }

    protected function downloadPaymentAccountPdf(): mixed
    {
        $rows = $this->exportPaymentAccountRows();

        if ($rows === null) {
            return null;
        }

        $service = app(PaymentAccountLedgerReportService::class);
        $accountId = $this->{$this->accountIdProperty()};

        return PdfDownload::streamed(
            app(PaymentAccountLedgerPdfService::class)->movement(
                reportTitle: $this->excelReportTitle(),
                accountName: $this->accountName($accountId) ?? '',
                rows: $rows,
                dateFrom: $this->dateFrom,
                dateTo: $this->dateTo,
                openingBalance: $this->openingBalance($service),
                periodTotals: $this->periodTotals($service),
            ),
        );
    }

    /**
     * @return Collection<int, CashBoxLedgerEntry|BankAccountLedgerEntry>|null
     */
    protected function exportPaymentAccountRows(): ?Collection
    {
        if (! $this->validateAccountSelected()) {
            return null;
        }

        $rows = $this->movementQuery(app(PaymentAccountLedgerReportService::class))->get();

        if ($rows->isEmpty()) {
            Notification::make()
                ->title('لا توجد بيانات للتصدير')
                ->warning()
                ->send();

            return null;
        }

        return $rows;
    }
}
