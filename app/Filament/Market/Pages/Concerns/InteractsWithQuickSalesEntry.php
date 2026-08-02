<?php

namespace App\Filament\Market\Pages\Concerns;

use App\Filament\Market\Pages\QuickSell\Schemas\QuickSalesHeaderForm;
use App\Filament\Market\Pages\QuickSell\Schemas\QuickSalesLineForm;
use App\Filament\Market\Pages\QuickSell\Schemas\QuickSalesStoreForm;
use App\Models\CashBox;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\SalesInvoiceLineWork;
use App\Services\Inventory\SalesInventoryService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

trait InteractsWithQuickSalesEntry
{
    use InteractsWithSalesEntry {
        InteractsWithSalesEntry::refreshPaymentTotals as protected refreshStandardPaymentTotals;
    }

    public function isQuickSell(): bool
    {
        return true;
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

    public function hasInvoiceLines(): bool
    {
        return SalesInvoiceLineWork::query()
            ->where('sales_invoice_work_id', Auth::id())
            ->exists();
    }

    public function defaultCashBoxId(): ?int
    {
        $assigned = CashBox::query()
            ->where('is_active', true)
            ->where('assigned_user_id', Auth::id())
            ->value('id');

        if ($assigned) {
            return (int) $assigned;
        }

        $first = CashBox::query()
            ->where('is_active', true)
            ->value('id');

        return $first ? (int) $first : null;
    }

    public function hasAssignedCashBox(): bool
    {
        return CashBox::query()
            ->where('is_active', true)
            ->where('assigned_user_id', Auth::id())
            ->exists();
    }

    public function headerForm(Schema $schema): Schema
    {
        return QuickSalesHeaderForm::configure($schema, $this);
    }

    public function lineForm(Schema $schema): Schema
    {
        return QuickSalesLineForm::configure($schema, $this);
    }

    public function storeForm(Schema $schema): Schema
    {
        return QuickSalesStoreForm::configure($schema, $this);
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

        $this->quickCommitLine((int) $itemId, $barcode);
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

        $this->quickCommitLine($itemId, $item->barcode);
    }

    protected function quickCommitLine(int $itemId, ?string $barcode): void
    {
        $inventory = app(SalesInventoryService::class);
        $warehouseId = $this->currentWarehouseId();

        if (! $warehouseId) {
            Notification::make()->title('يجب اختيار مخزن')->warning()->send();

            return;
        }

        $entryQty = (float) ($this->lineData['qty_primary'] ?? 1);

        if ($entryQty <= 0) {
            Notification::make()->title('يجب ادخال الكمية')->warning()->send();
            $this->dispatch('focus-field', field: 'qty_primary');

            return;
        }

        $stock = $inventory->warehouseStockQty($itemId, $warehouseId);

        if ($stock <= 0) {
            Notification::make()->title('الصنف غير مخزون في نقطة البيع هذه')->warning()->send();

            return;
        }

        $existing = SalesInvoiceLineWork::query()
            ->where('sales_invoice_work_id', Auth::id())
            ->where('item_id', $itemId)
            ->first();

        $finalQty = $existing
            ? (float) $existing->qty_primary + $entryQty
            : $entryQty;

        if ($finalQty > $stock) {
            Notification::make()->title('الرصيد لا يسمح')->warning()->send();

            return;
        }

        $entryPrice = (float) ($this->lineData['unit_price_primary'] ?? 0);

        if ($existing) {
            $unitPrice = (float) $existing->unit_price_primary;
        } elseif ($entryPrice > 0) {
            $unitPrice = $entryPrice;
        } else {
            $unitPrice = (float) (Item::query()->find($itemId)?->sellPriceFor($this->currentPaymentMethodId()) ?? 0);
        }

        if ($unitPrice <= 0) {
            Notification::make()->title('سعر البيع لا يجوز أن يكون صفر')->warning()->send();
            $this->dispatch('focus-field', field: 'unit_price_primary');

            return;
        }

        $linePayload = [
            'item_id' => $itemId,
            'barcode' => $barcode,
            'qty_primary' => $finalQty,
            'unit_price_primary' => $unitPrice,
            'line_total' => $finalQty * $unitPrice,
            'sales_invoice_work_id' => Auth::id(),
            'created_by' => Auth::id(),
        ];

        if ($existing) {
            $existing->update($linePayload);
        } else {
            SalesInvoiceLineWork::query()->create($linePayload);
        }

        $this->recalculateTotals();
        $this->resetQuickLineEntry();
    }

    public function resetQuickLineEntry(): void
    {
        $this->lineForm->fill([
            'qty_primary' => 1,
            'barcode' => null,
            'item_id' => null,
            'unit_price_primary' => null,
            'stock_display' => null,
        ]);

        $this->dispatch('focus-field', field: 'barcode');
    }

    public function refreshPaymentTotals(): void
    {
        $subtotal = (float) SalesInvoiceLineWork::query()
            ->where('sales_invoice_work_id', Auth::id())
            ->sum('line_total');

        $this->work->fill([
            'lines_subtotal' => $subtotal,
            'extra_cost' => 0,
            'discount' => 0,
            'rate_markup' => 0,
            'difference_amount' => 0,
            'grand_total' => $subtotal,
            'amount_paid' => $subtotal,
            'balance' => 0,
        ]);
        $this->work->save();

        $this->headerForm->fill($this->work->toArray());
    }

    public function recalculateTotals(): void
    {
        $this->refreshPaymentTotals();
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
                    ->sortable()
                    ->tooltip('اضغط لتعديل الكمية')
                    ->action(
                        Action::make('updateQuantity')
                            ->fillForm(fn (SalesInvoiceLineWork $record): array => [
                                'qty_primary' => $record->qty_primary,
                            ])
                            ->schema([
                                Text::make(function (SalesInvoiceLineWork $record): HtmlString {
                                    $stock = app(SalesInventoryService::class)->warehouseStockQty(
                                        (int) $record->item_id,
                                        (int) $this->currentWarehouseId(),
                                    );

                                    return new HtmlString(
                                        '<br><strong class="text-indigo-700">الرصيد: </strong>'.$stock
                                    );
                                }),
                                TextInput::make('qty_primary')
                                    ->label('الكمية الجديدة')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1),
                            ])
                            ->modalWidth(Width::Medium)
                            ->modalHeading('تعديل الكمية')
                            ->action(function (SalesInvoiceLineWork $record, array $data): void {
                                $qty = (float) ($data['qty_primary'] ?? 0);

                                if ($qty <= 0) {
                                    Notification::make()->title('يجب ادخال الكمية')->danger()->send();

                                    return;
                                }

                                $warehouseId = $this->currentWarehouseId();

                                if (! $warehouseId) {
                                    Notification::make()->title('يجب اختيار مخزن')->warning()->send();

                                    return;
                                }

                                $stock = app(SalesInventoryService::class)->warehouseStockQty(
                                    (int) $record->item_id,
                                    $warehouseId,
                                );

                                if ($qty > $stock) {
                                    Notification::make()->title('الرصيد لا يسمح')->warning()->send();

                                    return;
                                }

                                $record->update([
                                    'qty_primary' => $qty,
                                    'line_total' => $qty * (float) $record->unit_price_primary,
                                ]);

                                $this->recalculateTotals();
                                $this->dispatch('focus-field', field: 'barcode');
                            }),
                    ),
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
                Action::make('delete')
                    ->action(function (SalesInvoiceLineWork $record): void {
                        $record->delete();
                        $this->recalculateTotals();
                        $this->resetQuickLineEntry();
                    })
                    ->icon('heroicon-m-trash')
                    ->iconButton()
                    ->color('danger')
                    ->hiddenLabel()
                    ->hidden(fn (): bool => SalesInvoiceLineWork::query()
                        ->where('sales_invoice_work_id', Auth::id())
                        ->count() <= 1)
                    ->requiresConfirmation(),
            ])
            ->emptyStateHeading('لم يتم ادخال اصناف')
            ->striped();
    }
}
