<?php

namespace App\Filament\Market\Resources\Concerns;

use App\Enums\ReceiptListKind;
use App\Services\Excel\MarketExcelService;
use App\Services\Market\PaymentReceiptListService;
use App\Services\Pdf\PaymentReceiptListPdfService;
use App\Support\PdfDownload;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

trait InteractsWithPaymentReceiptListExports
{
    abstract protected function receiptListKind(): ReceiptListKind;

    protected function downloadReceiptListPdf(): mixed
    {
        $rows = $this->exportReceiptListRows();

        if ($rows === null) {
            return null;
        }

        $kind = $this->receiptListKind();
        $service = app(PaymentReceiptListService::class);

        return PdfDownload::streamed(
            app(PaymentReceiptListPdfService::class)->list(
                $kind,
                $rows,
                $service->exportMeta($this, $kind),
            ),
        );
    }

    protected function downloadReceiptListExcel(): mixed
    {
        $rows = $this->exportReceiptListRows();

        if ($rows === null) {
            return null;
        }

        $kind = $this->receiptListKind();

        return app(MarketExcelService::class)->paymentReceiptList(
            $rows,
            $kind,
            app(PaymentReceiptListService::class)->exportMeta($this, $kind),
        );
    }

    /**
     * @return Collection<int, \Illuminate\Database\Eloquent\Model>|null
     */
    protected function exportReceiptListRows(): ?Collection
    {
        $rows = $this->getTableQueryForExport()
            ->with($this->receiptListKind()->eagerLoads())
            ->get();

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
