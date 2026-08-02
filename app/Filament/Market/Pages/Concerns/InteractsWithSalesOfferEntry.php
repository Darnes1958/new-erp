<?php

namespace App\Filament\Market\Pages\Concerns;

use App\Filament\Market\Pages\InpSellOffer\Schemas\SalesOfferHeaderForm;
use App\Filament\Market\Pages\InpSellOffer\Schemas\SalesOfferLineForm;
use App\Filament\Market\Pages\InpSellOffer\Schemas\SalesOfferStoreForm;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\SalesOfferInvoiceLineWork;
use App\Models\SalesOfferInvoiceWork;
use App\Models\Warehouse;
use App\Services\Inventory\SalesInventoryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

trait InteractsWithSalesOfferEntry
{
    public SalesOfferInvoiceWork $work;

    public array $headerData = [];

    public array $lineData = [];

    public array $storeData = [];

    public function isEditMode(): bool
    {
        return false;
    }

    public function usesInstallmentMarkup(): bool
    {
        $paymentMethodId = (int) ($this->headerData['payment_method_id'] ?? $this->work->payment_method_id ?? 1);

        return $paymentMethodId === 3;
    }

    public function canEditSellPrice(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->is_prog) {
            return true;
        }

        return $user->can('تعديل السعر');
    }

    public function headerForm(Schema $schema): Schema
    {
        return SalesOfferHeaderForm::configure($schema, $this);
    }

    public function lineForm(Schema $schema): Schema
    {
        return SalesOfferLineForm::configure($schema, $this);
    }

    public function storeForm(Schema $schema): Schema
    {
        return SalesOfferStoreForm::configure($schema, $this);
    }

    public function refreshOfferTotals(): void
    {
        $state = $this->headerForm->getState();
        $subtotal = (float) $this->work->lines_subtotal;
        $extraCost = (float) ($state['extra_cost'] ?? 0);
        $rateMarkup = (float) ($state['rate_markup'] ?? 0);
        $paymentMethodId = (int) ($state['payment_method_id'] ?? $this->work->payment_method_id ?? 1);

        $differenceAmount = $paymentMethodId === 3
            ? ($subtotal + $extraCost) * $rateMarkup / 100
            : 0;

        $grandTotal = $subtotal + $extraCost + $differenceAmount;

        $this->work->fill([
            ...$state,
            'lines_subtotal' => $subtotal,
            'extra_cost' => $extraCost,
            'rate_markup' => $rateMarkup,
            'difference_amount' => $differenceAmount,
            'grand_total' => $grandTotal,
        ]);
        $this->work->save();
        $this->headerForm->fill($this->work->toArray());
    }

    public function checkBarcode(?string $barcode): void
    {
        if ($barcode === null || trim($barcode) === '') {
            return;
        }

        $itemId = Item::query()->where('barcode', $barcode)->value('id')
            ?? ItemBarcode::query()->where('barcode', $barcode)->value('item_id');

        if (! $itemId) {
            Notification::make()
                ->title('هذا الباركود غير مخزون')
                ->warning()
                ->send();

            return;
        }

        $this->fillLineForItem((int) $itemId, $barcode);
    }

    public function submitBarcode(): void
    {
        $barcode = trim((string) ($this->lineData['barcode'] ?? ''));

        if ($barcode === '') {
            return;
        }

        $this->checkBarcode($barcode);
    }

    public function checkItem(?int $itemId): void
    {
        if (! $itemId) {
            return;
        }

        $item = Item::query()->find($itemId);

        if (! $item) {
            return;
        }

        $this->fillLineForItem($itemId, $item->barcode);
    }

    public function fillLineForItem(int $itemId, ?string $barcode): void
    {
        if (! ($this->headerData['payment_method_id'] ?? $this->work->payment_method_id)) {
            Notification::make()->title('يجب اختيار طريقة دفع')->warning()->send();

            return;
        }

        $unitPrice = Item::query()->find($itemId)?->sellPriceFor($this->currentPaymentMethodId()) ?? 0;
        $warehouseId = $this->currentWarehouseId();
        $stock = $warehouseId
            ? app(SalesInventoryService::class)->warehouseStockQty($itemId, $warehouseId)
            : 0;

        $existing = SalesOfferInvoiceLineWork::query()
            ->where('sales_offer_invoice_work_id', Auth::id())
            ->where('item_id', $itemId)
            ->first();

        if ($existing) {
            $this->lineForm->fill(array_merge($existing->toArray(), [
                'stock_display' => $stock,
            ]));

            $this->focusAfterItemResolved((float) $existing->unit_price_primary);

            return;
        }

        $this->lineForm->fill([
            'barcode' => $barcode,
            'item_id' => $itemId,
            'unit_price_primary' => $unitPrice > 0 ? $unitPrice : null,
            'qty_primary' => '',
            'stock_display' => $stock,
            'sales_offer_invoice_work_id' => Auth::id(),
            'created_by' => Auth::id(),
        ]);

        $this->focusAfterItemResolved($unitPrice > 0 ? (float) $unitPrice : null);
    }

    public function focusAfterItemResolved(?float $unitPrice): void
    {
        if ($unitPrice === null || $unitPrice <= 0) {
            $this->dispatch('focus-field', field: 'unit_price_primary');

            return;
        }

        $this->dispatch('focus-field', field: 'qty_primary');
    }

    public function focusQuantity(): void
    {
        $this->dispatch('focus-field', field: 'qty_primary');
    }

    public function refreshLineSellPrice(): void
    {
        $itemId = (int) ($this->lineData['item_id'] ?? 0);

        if ($itemId <= 0) {
            return;
        }

        $unitPrice = Item::query()->find($itemId)?->sellPriceFor($this->currentPaymentMethodId()) ?? 0;

        $this->lineForm->fill(array_merge($this->lineData, [
            'unit_price_primary' => $unitPrice > 0 ? $unitPrice : null,
        ]));
    }

    public function currentPaymentMethodId(): int
    {
        return (int) ($this->headerData['payment_method_id'] ?? $this->work->payment_method_id ?? 1);
    }

    public function currentWarehouseId(): ?int
    {
        return (int) ($this->headerData['warehouse_id'] ?? $this->work->warehouse_id ?? $this->defaultWarehouseId() ?? 0) ?: null;
    }

    public function addLine(): void
    {
        $this->lineForm->validate();

        $data = $this->lineForm->getState();

        $linePayload = collect($data)->except(['stock_display'])->toArray();
        $linePayload['line_total'] = (float) $linePayload['qty_primary'] * (float) $linePayload['unit_price_primary'];
        $linePayload['sales_offer_invoice_work_id'] = Auth::id();
        $linePayload['created_by'] = Auth::id();

        $line = SalesOfferInvoiceLineWork::query()
            ->where('sales_offer_invoice_work_id', Auth::id())
            ->where('item_id', $linePayload['item_id'])
            ->first();

        if ($line) {
            $line->update($linePayload);
        } else {
            SalesOfferInvoiceLineWork::query()->create($linePayload);
        }

        $this->recalculateTotals();
        $this->lineForm->fill([]);
        $this->dispatch('focus-field', field: 'barcode');
    }

    public function recalculateTotals(): void
    {
        $subtotal = (float) SalesOfferInvoiceLineWork::query()
            ->where('sales_offer_invoice_work_id', Auth::id())
            ->sum('line_total');

        $this->work->lines_subtotal = $subtotal;
        $this->work->save();
        $this->refreshOfferTotals();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => SalesOfferInvoiceLineWork::query()->where('sales_offer_invoice_work_id', Auth::id()))
            ->columns([
                TextColumn::make('item_id')
                    ->label('رقم الصنف')
                    ->sortable(),
                TextColumn::make('barcode')
                    ->label('الباركود')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item.name')
                    ->label('اسم الصنف')
                    ->searchable()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('qty_primary')
                    ->label('الكمية')
                    ->sortable(),
                TextColumn::make('unit_price_primary')
                    ->label('السعر')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('line_total')
                    ->label('المجموع')
                    ->numeric(3)
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('edit_line')
                    ->action(function (SalesOfferInvoiceLineWork $record): void {
                        $warehouseId = $this->currentWarehouseId();
                        $stock = $warehouseId
                            ? app(SalesInventoryService::class)->warehouseStockQty((int) $record->item_id, $warehouseId)
                            : 0;

                        $this->lineForm->fill(array_merge($record->toArray(), [
                            'stock_display' => $stock,
                        ]));

                        $this->dispatch('focus-field', field: 'qty_primary');
                    })
                    ->icon('heroicon-m-pencil')
                    ->iconButton()
                    ->color('info')
                    ->hiddenLabel(),
                Action::make('delete')
                    ->action(function (SalesOfferInvoiceLineWork $record): void {
                        $record->delete();
                        $this->recalculateTotals();
                        $this->lineForm->fill([]);
                    })
                    ->icon('heroicon-m-trash')
                    ->iconButton()
                    ->color('danger')
                    ->hiddenLabel()
                    ->hidden(fn (): bool => SalesOfferInvoiceLineWork::query()
                        ->where('sales_offer_invoice_work_id', Auth::id())
                        ->count() <= 1)
                    ->requiresConfirmation(),
            ])
            ->emptyStateHeading('لم يتم ادخال اصناف')
            ->striped();
    }

    protected function defaultWarehouseId(): ?int
    {
        $userWarehouse = Auth::user()?->warehouse_id;

        if ($userWarehouse) {
            return (int) $userWarehouse;
        }

        return Warehouse::query()->where('is_active', true)->value('id');
    }

    protected function initializeOfferWorkDraft(): void
    {
        $this->work = SalesOfferInvoiceWork::query()->firstOrCreate(
            ['id' => Auth::id()],
            [
                'user_id' => Auth::id(),
                'warehouse_id' => $this->defaultWarehouseId(),
                'is_retail' => true,
            ],
        );

        if (! $this->work->warehouse_id) {
            $this->work->warehouse_id = $this->defaultWarehouseId();
            $this->work->save();
        }
    }
}
