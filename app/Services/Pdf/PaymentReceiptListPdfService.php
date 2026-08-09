<?php

namespace App\Services\Pdf;

use App\Enums\ReceiptListKind;
use App\Models\OurCompany;
use App\Services\Market\PaymentReceiptListService;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class PaymentReceiptListPdfService
{
    /**
     * @param  Collection<int, \Illuminate\Database\Eloquent\Model>  $rows
     * @param  array{
     *     dateFrom: ?string,
     *     dateTo: ?string,
     *     partyName: ?string,
     *     warehouseName: ?string,
     *     kindFilterLabel: ?string
     * }  $meta
     */
    public function list(
        ReceiptListKind $kind,
        Collection $rows,
        array $meta,
        ?OurCompany $company = null,
    ): PdfBuilder {
        $service = app(PaymentReceiptListService::class);

        return Pdf::view('pdf.market.invoice-list', [
            'company' => $company ?? OurCompany::forCurrentUser(),
            'reportTitle' => $kind->reportTitle(),
            'dateFrom' => $meta['dateFrom'],
            'dateTo' => $meta['dateTo'],
            'partyLabel' => $kind->partyLabel(),
            'partyName' => $meta['partyName'],
            'warehouseName' => $meta['warehouseName'],
            'tabLabel' => $meta['kindFilterLabel'],
            'filterLabel' => 'النوع',
            'headers' => $service->headers($kind),
            'rows' => $service->displayRows($rows, $kind),
            'totals' => $rows->isNotEmpty() ? $service->totalsRow($rows) : [],
        ])->name($kind->downloadFileStem().'.pdf');
    }
}
