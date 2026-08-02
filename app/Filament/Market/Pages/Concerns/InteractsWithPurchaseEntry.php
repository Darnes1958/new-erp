<?php

namespace App\Filament\Market\Pages\Concerns;

use App\Filament\Market\Pages\InpBuy\Schemas\ItemQuickForm;
use App\Filament\Market\Pages\InpBuy\Schemas\PurchaseHeaderForm;
use App\Filament\Market\Pages\InpBuy\Schemas\PurchaseLineForm;
use App\Filament\Market\Pages\InpBuy\Schemas\PurchaseStoreForm;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseInvoiceLineWork;
use App\Models\SupplierPayment;
use App\Models\PurchaseInvoiceWork;
use App\Models\Warehouse;
use App\Services\Inventory\PurchaseInventoryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

trait InteractsWithPurchaseEntry
{
    public PurchaseInvoiceWork $work;

    public array $headerData = [];

    public array $lineData = [];

    public array $storeData = [];

    public function isEditMode(): bool
    {
        return false;
    }

    public function headerForm(Schema $schema): Schema
    {
        return PurchaseHeaderForm::configure($schema, $this);
    }

    public function lineForm(Schema $schema): Schema
    {
        return PurchaseLineForm::configure($schema, $this);
    }

    public function storeForm(Schema $schema): Schema
    {
        return PurchaseStoreForm::configure($schema, $this);
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
        $entryPaid = (float) ($state['amount_paid'] ?? 0);
        $discount = (float) ($state['discount'] ?? 0);

        $this->work->fill([
            ...$state,
            'amount_paid' => $entryPaid,
            'discount' => $discount,
            'balance' => $this->calculateDisplayBalance($subtotal, $entryPaid, $discount),
        ]);
        $this->work->save();
        $this->headerForm->fill($this->work->toArray());
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
        $item = Item::query()->findOrFail($itemId);
        $paymentMethodId = $this->currentPaymentMethodId();
        $unitCost = Item::resolveBuyPrice($itemId, $paymentMethodId);

        $existing = PurchaseInvoiceLineWork::query()
            ->where('purchase_invoice_work_id', Auth::id())
            ->where('item_id', $itemId)
            ->first();

        if ($existing) {
            $this->lineForm->fill(array_merge($existing->toArray(), [
                'price_nakdy' => $item->sellPriceFor(1),
                'price_takseet' => $item->sellPriceFor(3),
            ]));

            $this->focusAfterItemResolved((float) $existing->unit_cost_primary);

            return;
        }

        $resolvedUnitCost = $unitCost > 0 ? $unitCost : null;

        $this->lineForm->fill([
            'barcode' => $barcode,
            'item_id' => $itemId,
            'unit_cost_primary' => $resolvedUnitCost,
            'qty_primary' => '',
            'purchase_invoice_work_id' => Auth::id(),
            'created_by' => Auth::id(),
            'price_nakdy' => $item->sellPriceFor(1),
            'price_takseet' => $item->sellPriceFor(3),
        ]);

        $this->focusAfterItemResolved($resolvedUnitCost !== null ? (float) $resolvedUnitCost : null);
    }

    public function focusAfterItemResolved(?float $unitCost): void
    {
        if ($unitCost === null || $unitCost <= 0) {
            $this->dispatch('focus-field', field: 'unit_cost_primary');

            return;
        }

        $this->dispatch('focus-field', field: 'qty_primary');
    }

    public function focusQuantity(): void
    {
        $this->dispatch('focus-field', field: 'qty_primary');
    }

    public function focusUnitCost(): void
    {
        $this->dispatch('focus-field', field: 'unit_cost_primary');
    }

    public function saveCashSellPrice(): void
    {
        $this->saveSellPrice(
            paymentMethodId: 1,
            field: 'price_nakdy',
            label: 'نقداً',
            nextFocus: 'price_takseet',
        );
    }

    public function saveInstallmentSellPrice(): void
    {
        $this->saveSellPrice(
            paymentMethodId: 3,
            field: 'price_takseet',
            label: 'تقسيط',
            nextFocus: 'price_nakdy',
        );
    }

    protected function saveSellPrice(int $paymentMethodId, string $field, string $label, string $nextFocus): void
    {
        $itemId = (int) ($this->lineData['item_id'] ?? 0);

        if ($itemId <= 0) {
            Notification::make()
                ->title('يجب اختيار صنف أولاً')
                ->warning()
                ->send();

            return;
        }

        $price = $this->lineData[$field] ?? null;

        if (! filled($price) || (float) $price <= 0) {
            Notification::make()
                ->title('يجب إدخال سعر بيع أكبر من صفر')
                ->warning()
                ->send();

            return;
        }

        app(PurchaseInventoryService::class)->updateSellPrice($itemId, $paymentMethodId, (float) $price);

        Notification::make()
            ->title("تم تحديث سعر البيع {$label}")
            ->success()
            ->send();

        $this->dispatch('focus-field', field: $nextFocus);
    }

    public function refreshLineBuyPrice(): void
    {
        $itemId = (int) ($this->lineData['item_id'] ?? 0);

        if ($itemId <= 0) {
            return;
        }

        $unitCost = Item::resolveBuyPrice($itemId, $this->currentPaymentMethodId());

        $this->lineForm->fill(array_merge($this->lineData, [
            'unit_cost_primary' => $unitCost > 0 ? $unitCost : null,
        ]));
    }

    public function currentPaymentMethodId(): int
    {
        return (int) ($this->headerData['payment_method_id'] ?? $this->work->payment_method_id ?? 1);
    }

    public function addLine(): void
    {
        $inventory = app(PurchaseInventoryService::class);

        $this->lineForm->validate();

        $data = $this->lineForm->getState();

        if (filled($data['price_nakdy'] ?? null)) {
            $inventory->updateSellPrice((int) $data['item_id'], 1, (float) $data['price_nakdy']);
        }

        if (filled($data['price_takseet'] ?? null)) {
            $inventory->updateSellPrice((int) $data['item_id'], 3, (float) $data['price_takseet']);
        }

        $linePayload = collect($data)->except(['price_nakdy', 'price_takseet'])->toArray();
        $linePayload['line_cost_total'] = (float) $linePayload['qty_primary'] * (float) $linePayload['unit_cost_primary'];
        $linePayload['purchase_invoice_work_id'] = Auth::id();
        $linePayload['created_by'] = Auth::id();

        $line = PurchaseInvoiceLineWork::query()
            ->where('purchase_invoice_work_id', Auth::id())
            ->where('item_id', $linePayload['item_id'])
            ->first();

        if ($line) {
            $line->update($linePayload);
        } else {
            PurchaseInvoiceLineWork::query()->create($linePayload);
        }

        $this->recalculateTotals();
        $this->lineForm->fill([]);
        $this->dispatch('focus-field', field: 'barcode');
    }

    public function recalculateTotals(): void
    {
        $subtotal = (float) PurchaseInvoiceLineWork::query()
            ->where('purchase_invoice_work_id', Auth::id())
            ->sum('line_cost_total');

        $entryPaid = (float) ($this->headerData['amount_paid'] ?? $this->work->amount_paid);
        $discount = (float) ($this->headerData['discount'] ?? $this->work->discount);

        $this->work->lines_subtotal = $subtotal;
        $this->work->amount_paid = $entryPaid;
        $this->work->discount = $discount;
        $this->work->balance = $this->calculateDisplayBalance($subtotal, $entryPaid, $discount);
        $this->work->save();
        $this->headerForm->fill($this->work->toArray());
    }

    protected function calculateDisplayBalance(float $subtotal, float $entryPaid, float $discount = 0): float
    {
        $netTotal = $subtotal - $discount;

        if ($this->isEditMode() && property_exists($this, 'invoice')) {
            $existingTotal = PurchaseInvoice::totalPaymentsForInvoice((int) $this->invoice->id);
            $existingKind5 = (float) (SupplierPayment::query()
                ->where('purchase_invoice_id', $this->invoice->id)
                ->where('transaction_kind', 5)
                ->value('amount') ?? 0);

            return $netTotal - ($existingTotal - $existingKind5 + $entryPaid);
        }

        return $netTotal - $entryPaid;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => PurchaseInvoiceLineWork::query()->where('purchase_invoice_work_id', Auth::id()))
            ->columns([
                TextColumn::make('item_id')
                    ->label('رقم الصنف')
                    ->sortable()
                    ->action(
                        Action::make('edit_item')
                            ->model(Item::class)
                            ->modalHeading('تعديل الصنف')
                            ->schema(ItemQuickForm::schema(forEdit: true))
                            ->fillForm(function (PurchaseInvoiceLineWork $record): array {
                                $item = Item::query()->findOrFail($record->item_id);

                                return ItemQuickForm::itemToFormData($item);
                            })
                            ->action(function (PurchaseInvoiceLineWork $record, array $data): void {
                                $item = Item::query()->findOrFail($record->item_id);
                                ItemQuickForm::updateFromData($item, $data);

                                $record->update(['barcode' => $item->barcode]);

                                if ((int) ($this->lineData['item_id'] ?? 0) === $item->id) {
                                    $this->checkItem($item->id);
                                }

                                Notification::make()
                                    ->title('تم تعديل الصنف')
                                    ->success()
                                    ->send();
                            })
                    ),
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
                TextColumn::make('unit_cost_primary')
                    ->label('سعر الشراء')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('line_cost_total')
                    ->label('المجموع')
                    ->numeric(3)
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('edit_line')
                    ->action(function (PurchaseInvoiceLineWork $record): void {
                        $item = Item::query()->find($record->item_id);

                        $this->lineForm->fill(array_merge($record->toArray(), [
                            'price_nakdy' => $item?->sellPriceFor(1),
                            'price_takseet' => $item?->sellPriceFor(3),
                        ]));

                        $this->dispatch('focus-field', field: 'qty_primary');
                    })
                    ->icon('heroicon-m-pencil')
                    ->iconButton()
                    ->color('info')
                    ->hiddenLabel(),
                Action::make('delete')
                    ->action(function (PurchaseInvoiceLineWork $record): void {
                        if ($this->isEditMode() && $record->source_purchase_invoice_line_id) {
                            $original = PurchaseInvoiceLine::query()->find($record->source_purchase_invoice_line_id);

                            if ($original) {
                                $consumed = (float) $original->qty_primary - (float) $original->remaining_qty_primary;

                                if ($consumed > 0.0001) {
                                    Notification::make()
                                        ->title('لا يمكن حذف صنف تم صرف جزء منه')
                                        ->warning()
                                        ->send();

                                    return;
                                }
                            }
                        }

                        $record->delete();
                        $this->recalculateTotals();
                        $this->lineForm->fill([]);
                    })
                    ->icon('heroicon-m-trash')
                    ->iconButton()
                    ->color('danger')
                    ->hiddenLabel()
                    ->hidden(fn (): bool => PurchaseInvoiceLineWork::query()
                        ->where('purchase_invoice_work_id', Auth::id())
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

    protected function initializeWorkDraft(): void
    {
        $this->work = PurchaseInvoiceWork::query()->firstOrCreate(
            ['id' => Auth::id()],
            [
                'user_id' => Auth::id(),
                'payment_method_id' => 1,
                'warehouse_id' => $this->defaultWarehouseId(),
            ],
        );

        if (! $this->work->warehouse_id) {
            $this->work->warehouse_id = $this->defaultWarehouseId();
            $this->work->save();
        }
    }
}
