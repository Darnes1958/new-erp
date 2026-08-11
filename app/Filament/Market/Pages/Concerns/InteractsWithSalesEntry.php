<?php

namespace App\Filament\Market\Pages\Concerns;

use App\Filament\Market\Pages\InpSell\Schemas\SalesHeaderForm;
use App\Filament\Market\Pages\InpSell\Schemas\SalesLineForm;
use App\Filament\Market\Pages\InpSell\Schemas\SalesStoreForm;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\SalesInvoiceLineWork;
use App\Models\SalesInvoiceWork;
use App\Models\Warehouse;
use App\Services\Inventory\SalesInventoryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

trait InteractsWithSalesEntry
{
    public SalesInvoiceWork $work;

    public array $headerData = [];

    public array $lineData = [];

    public array $storeData = [];

    public function isEditMode(): bool
    {
        return false;
    }

    public function locksPaymentMethod(): bool
    {
        return false;
    }

    public function usesMinimalSalesHeader(): bool
    {
        return false;
    }

    public function usesInstallmentMarkup(): bool
    {
        $paymentMethodId = (int) ($this->headerData['payment_method_id'] ?? $this->work->payment_method_id ?? 1);

        return $paymentMethodId === 3;
    }

    public function headerForm(Schema $schema): Schema
    {
        return SalesHeaderForm::configure($schema, $this);
    }

    public function lineForm(Schema $schema): Schema
    {
        return SalesLineForm::configure($schema, $this);
    }

    public function storeForm(Schema $schema): Schema
    {
        return SalesStoreForm::configure($schema, $this);
    }

    public function updatePay(): void
    {
        $this->refreshPaymentTotals();

        Notification::make()
            ->title('تم تحزين البيانات بنجاح')
            ->success()
            ->send();
    }

    public function refreshPaymentTotals(): void
    {
        $state = $this->headerForm->getState();
        $subtotal = (float) $this->work->lines_subtotal;

        if ($this->usesMinimalSalesHeader()) {
            $extraCost = 0;
            $discount = 0;
            $rateMarkup = 0;
            $entryPaid = 0;
            $differenceAmount = 0;
            $grandTotal = $subtotal;
        } else {
            $extraCost = (float) ($state['extra_cost'] ?? 0);
            $discount = (float) ($state['discount'] ?? 0);
            $rateMarkup = (float) ($state['rate_markup'] ?? 0);
            $entryPaid = (float) ($state['amount_paid'] ?? 0);
            $paymentMethodId = (int) ($state['payment_method_id'] ?? $this->work->payment_method_id ?? 1);

            $differenceAmount = $paymentMethodId === 3
                ? ($subtotal + $extraCost) * $rateMarkup / 100
                : 0;

            $grandTotal = $subtotal + $extraCost - $discount + $differenceAmount;
        }

        $this->work->fill([
            ...$state,
            'lines_subtotal' => $subtotal,
            'extra_cost' => $extraCost,
            'discount' => $discount,
            'rate_markup' => $rateMarkup,
            'difference_amount' => $differenceAmount,
            'grand_total' => $grandTotal,
            'amount_paid' => $entryPaid,
            'balance' => $this->calculateDisplayBalance($grandTotal, $entryPaid),
        ]);
        $this->work->save();
        $this->headerForm->fill($this->work->toArray());
    }

    protected function calculateDisplayBalance(float $grandTotal, float $entryPaid): float
    {
        return $grandTotal - $entryPaid;
    }

    public function checkBarcode(?string $barcode): void
    {
        if ($barcode === null || $barcode === '') {
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
        $warehouseId = $this->currentWarehouseId();

        if (! $warehouseId) {
            Notification::make()->title('يجب اختيار مخزن')->warning()->send();

            return;
        }

        $stock = app(SalesInventoryService::class)->warehouseStockQty($itemId, $warehouseId);

        $existing = SalesInvoiceLineWork::query()
            ->where('sales_invoice_work_id', Auth::id())
            ->where('item_id', $itemId)
            ->first();

        $availableStock = $stock + (float) ($existing?->qty_primary ?? 0);

        if ($availableStock <= 0) {
            Notification::make()->title('الصنف غير مخزون في نقطة البيع هذه')->warning()->send();

            return;
        }

        $item = Item::query()->findOrFail($itemId);
        $unitPrice = $item->sellPriceFor($this->currentPaymentMethodId()) ?? 0;

        if ($existing) {
            $this->lineForm->fill(array_merge($existing->toArray(), [
                'stock_display' => $availableStock,
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
            'sales_invoice_work_id' => Auth::id(),
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

    public function focusUnitPrice(): void
    {
        $this->dispatch('focus-field', field: 'unit_price_primary');
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
        $inventory = app(SalesInventoryService::class);

        $this->lineForm->validate();

        $data = $this->lineForm->getState();
        $warehouseId = $this->currentWarehouseId();

        if (! $warehouseId) {
            Notification::make()->title('يجب اختيار مخزن')->warning()->send();

            return;
        }

        try {
            $existingLine = SalesInvoiceLineWork::query()
                ->where('sales_invoice_work_id', Auth::id())
                ->where('item_id', (int) $data['item_id'])
                ->first();

            $existingQty = (float) ($existingLine?->qty_primary ?? 0);
            $available = $inventory->warehouseStockQty((int) $data['item_id'], $warehouseId) + $existingQty;

            if ($available + 0.0001 < (float) $data['qty_primary']) {
                throw new \RuntimeException('الرصيد لا يسمح');
            }
        } catch (\RuntimeException $exception) {
            Notification::make()->title($exception->getMessage())->warning()->send();

            return;
        }

        $linePayload = collect($data)->except(['stock_display'])->toArray();
        $linePayload['line_total'] = (float) $linePayload['qty_primary'] * (float) $linePayload['unit_price_primary'];
        $linePayload['sales_invoice_work_id'] = Auth::id();
        $linePayload['created_by'] = Auth::id();

        $line = SalesInvoiceLineWork::query()
            ->where('sales_invoice_work_id', Auth::id())
            ->where('item_id', $linePayload['item_id'])
            ->first();

        if ($line) {
            $line->update($linePayload);
        } else {
            SalesInvoiceLineWork::query()->create($linePayload);
        }

        $this->recalculateTotals();
        $this->lineForm->fill([]);
        $this->dispatch('focus-field', field: 'barcode');
    }

    public function recalculateTotals(): void
    {
        $subtotal = (float) SalesInvoiceLineWork::query()
            ->where('sales_invoice_work_id', Auth::id())
            ->sum('line_total');

        $this->work->lines_subtotal = $subtotal;
        $this->work->save();
        $this->refreshPaymentTotals();
    }

    protected function workLineCount(): int
    {
        return SalesInvoiceLineWork::query()
            ->where('sales_invoice_work_id', Auth::id())
            ->count();
    }

    protected function assertWorkHasAtLeastOneLine(): bool
    {
        if ($this->workLineCount() > 0) {
            return true;
        }

        Notification::make()
            ->title('يجب أن تحتوي الفاتورة على صنف واحد على الأقل')
            ->warning()
            ->send();

        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => SalesInvoiceLineWork::query()->where('sales_invoice_work_id', Auth::id()))
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
                    ->action(function (SalesInvoiceLineWork $record): void {
                        $warehouseId = $this->currentWarehouseId();
                        $inventory = app(SalesInventoryService::class);
                        $warehouseStock = $warehouseId
                            ? $inventory->warehouseStockQty((int) $record->item_id, $warehouseId)
                            : 0;
                        $lineQty = (float) $record->qty_primary;

                        $this->lineForm->fill(array_merge($record->toArray(), [
                            'stock_display' => $warehouseStock + $lineQty,
                        ]));

                        $this->dispatch('focus-field', field: 'qty_primary');
                    })
                    ->icon('heroicon-m-pencil')
                    ->iconButton()
                    ->color('info')
                    ->hiddenLabel(),
                Action::make('remove_line')
                    ->label('إلغاء الصنف')
                    ->action(function (SalesInvoiceLineWork $record): void {
                        $deletedItemId = (int) $record->item_id;
                        $record->delete();
                        $this->recalculateTotals();

                        if ((int) ($this->lineData['item_id'] ?? 0) === $deletedItemId) {
                            $this->lineForm->fill([]);
                        }

                        Notification::make()
                            ->title('تم إلغاء الصنف من الفاتورة')
                            ->success()
                            ->send();
                    })
                    ->icon('heroicon-m-trash')
                    ->iconButton()
                    ->color('danger')
                    ->hiddenLabel()
                    ->requiresConfirmation()
                    ->modalHeading('إلغاء الصنف')
                    ->modalDescription('هل تريد إزالة هذا الصنف من الفاتورة؟')
                    ->modalSubmitActionLabel('إلغاء الصنف')
                    ->modalCancelActionLabel('تراجع'),
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

    protected function initializeWorkDraft(): void
    {
        $this->work = SalesInvoiceWork::query()->firstOrCreate(
            ['id' => Auth::id()],
            [
                'user_id' => Auth::id(),
                'payment_method_id' => 1,
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
