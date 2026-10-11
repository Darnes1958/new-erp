<?php

namespace App\Filament\Market\Pages\Reports\Concerns;

use App\Services\Excel\MarketExcelService;
use App\Services\Market\PaymentAccountLedgerReportService;
use App\Services\Pdf\PaymentAccountLedgerPdfService;
use App\Support\PdfDownload;
use Illuminate\Database\Eloquent\Builder;

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

    /**
     * @return array{carried: float, previous: float, debit: float, credit: float, balance: float, final: float}
     */
    abstract protected function statement(PaymentAccountLedgerReportService $service): array;

    abstract protected function reportTitle(): string;

    abstract protected function excelReportTitle(): string;

    abstract protected function validateAccountSelected(): bool;

    protected function downloadPaymentAccountExcel(): mixed
    {
        if (! $this->validateAccountSelected()) {
            return null;
        }

        $service = app(PaymentAccountLedgerReportService::class);
        $accountId = $this->{$this->accountIdProperty()};
        $statement = $this->statement($service);
        $rows = $service->attachRunningBalance($this->movementQuery($service)->get(), $statement['previous']);

        return app(MarketExcelService::class)->paymentAccountMovement(
            rows: $rows,
            reportTitle: $this->excelReportTitle(),
            accountName: $this->accountName($accountId) ?? '',
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            openingBalance: $statement['previous'],
            periodTotals: $statement,
        );
    }

    protected function downloadPaymentAccountPdf(): mixed
    {
        if (! $this->validateAccountSelected()) {
            return null;
        }

        $service = app(PaymentAccountLedgerReportService::class);
        $accountId = $this->{$this->accountIdProperty()};
        $statement = $this->statement($service);

        return PdfDownload::streamed(
            app(PaymentAccountLedgerPdfService::class)->movement(
                reportTitle: $this->excelReportTitle(),
                accountName: $this->accountName($accountId) ?? '',
                rows: $this->movementQuery($service)->get(),
                dateFrom: $this->dateFrom,
                dateTo: $this->dateTo,
                openingBalance: $statement['previous'],
                periodTotals: $statement,
            ),
        );
    }
}
