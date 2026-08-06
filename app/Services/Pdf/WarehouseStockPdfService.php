<?php

namespace App\Services\Pdf;

use App\Models\OurCompany;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class WarehouseStockPdfService
{
    /**
     * @param  Collection<int, \App\Models\WarehouseStockReportEntry>  $rows
     * @param  array{warehouse_cost_total: float}  $summary
     */
    public function report(
        Collection $rows,
        array $summary,
        ?string $warehouseName,
        bool $showCosts,
        bool $multiWarehouse,
    ): PdfBuilder {
        return Pdf::view('pdf.market.warehouse-stock', [
            'rows' => $rows,
            'summary' => $summary,
            'company' => OurCompany::forCurrentUser(),
            'warehouseName' => $warehouseName,
            'showCosts' => $showCosts,
            'multiWarehouse' => $multiWarehouse,
            'repDate' => now()->toDateString(),
        ])->name('warehouse-stock.pdf');
    }
}
