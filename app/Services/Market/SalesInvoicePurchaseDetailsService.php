<?php

namespace App\Services\Market;

use App\Models\FifoAllocation;
use App\Models\PurchaseInvoiceLine;
use App\Models\SalesInvoiceLineWork;
use Illuminate\Support\Collection;

class SalesInvoicePurchaseDetailsService
{
    /**
     * @return array{rows: list<array<string, mixed>>, purchase_total: float, sales_total: float}
     */
    public function forSalesInvoice(int $salesInvoiceId): array
    {
        $allocations = FifoAllocation::query()
            ->with(['item', 'salesInvoiceLine'])
            ->where('sales_invoice_id', $salesInvoiceId)
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($allocations as $allocation) {
            $qty = (float) $allocation->qty_primary;
            $buyPrice = (float) $allocation->unit_cost;
            $sellPrice = (float) ($allocation->salesInvoiceLine?->unit_price_primary ?? 0);

            $rows[] = $this->row(
                purchaseInvoiceId: $allocation->purchase_invoice_id ? (int) $allocation->purchase_invoice_id : null,
                itemId: (int) $allocation->item_id,
                itemName: (string) ($allocation->item?->name ?? ''),
                qty: $qty,
                buyPrice: $buyPrice,
                sellPrice: $sellPrice,
            );
        }

        return $this->payload($rows);
    }

    /**
     * @param  Collection<int, SalesInvoiceLineWork>|iterable<SalesInvoiceLineWork>  $lines
     * @return array{rows: list<array<string, mixed>>, purchase_total: float, sales_total: float}
     */
    public function forDraftLines(iterable $lines, int $warehouseId): array
    {
        $consumed = [];
        $rows = [];

        foreach ($lines as $line) {
            $need = (float) $line->qty_primary;
            $sellPrice = (float) $line->unit_price_primary;
            $itemId = (int) $line->item_id;
            $itemName = (string) ($line->item?->name ?? '');

            foreach ($this->fifoLayers($itemId, $warehouseId) as $layer) {
                if ($need <= 0.0001) {
                    break;
                }

                $available = (float) $layer->remaining_qty_primary - (float) ($consumed[$layer->id] ?? 0);

                if ($available <= 0.0001) {
                    continue;
                }

                $take = min($available, $need);
                $consumed[$layer->id] = (float) ($consumed[$layer->id] ?? 0) + $take;
                $need -= $take;

                $rows[] = $this->row(
                    purchaseInvoiceId: (int) $layer->purchase_invoice_id,
                    itemId: $itemId,
                    itemName: $itemName,
                    qty: $take,
                    buyPrice: (float) $layer->unit_cost_primary,
                    sellPrice: $sellPrice,
                );
            }

            if ($need > 0.0001) {
                $rows[] = $this->row(
                    purchaseInvoiceId: null,
                    itemId: $itemId,
                    itemName: $itemName,
                    qty: $need,
                    buyPrice: 0.0,
                    sellPrice: $sellPrice,
                );
            }
        }

        return $this->payload($rows);
    }

    /**
     * @return Collection<int, PurchaseInvoiceLine>
     */
    protected function fifoLayers(int $itemId, int $warehouseId): Collection
    {
        return PurchaseInvoiceLine::query()
            ->where('item_id', $itemId)
            ->where('remaining_qty_primary', '>', 0)
            ->whereHas('purchaseInvoice')
            ->with(['purchaseInvoice:id,warehouse_id,invoice_date', 'item:id,name'])
            ->get()
            ->sortBy([
                fn (PurchaseInvoiceLine $line): int => (int) $line->purchaseInvoice?->warehouse_id === $warehouseId ? 0 : 1,
                fn (PurchaseInvoiceLine $line) => (string) ($line->purchaseInvoice?->invoice_date ?? ''),
                fn (PurchaseInvoiceLine $line): int => (int) $line->id,
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(
        ?int $purchaseInvoiceId,
        int $itemId,
        string $itemName,
        float $qty,
        float $buyPrice,
        float $sellPrice,
    ): array {
        $purchaseTotal = $qty * $buyPrice;
        $salesTotal = $qty * $sellPrice;

        return [
            'purchase_invoice_id' => $purchaseInvoiceId,
            'item_id' => $itemId,
            'item_name' => $itemName,
            'qty' => $qty,
            'buy_price' => $buyPrice,
            'sell_price' => $sellPrice,
            'purchase_total' => $purchaseTotal,
            'sales_total' => $salesTotal,
            'margin' => $salesTotal - $purchaseTotal,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{rows: list<array<string, mixed>>, purchase_total: float, sales_total: float}
     */
    protected function payload(array $rows): array
    {
        return [
            'rows' => $rows,
            'purchase_total' => array_sum(array_column($rows, 'purchase_total')),
            'sales_total' => array_sum(array_column($rows, 'sales_total')),
        ];
    }
}
