<?php

namespace App\Filament\Market\Pages\InpBuy;

use App\Filament\Market\Pages\Concerns\InteractsWithPurchaseEntry;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseInvoiceLineWork;
use App\Models\PurchaseInvoiceWork;
use App\Models\SupplierPayment;
use App\Services\Inventory\PurchaseInventoryService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InpBuy extends Page implements HasSchemas, HasTable
{
    use InteractsWithPurchaseEntry {
        InteractsWithPurchaseEntry::table insteadof InteractsWithTable;
    }
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'فاتورة مشتريات جديدة';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->is_prog) {
            return true;
        }

        return $user->can('ادخال مشتريات');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::PurchaseInvoices;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'inp-buy';

    protected string $view = 'filament.market.pages.inp-buy';

    protected ?string $heading = '';

    public function mount(): void
    {
        $this->initializeWorkDraft();

        if ($this->work->source_purchase_invoice_id !== null) {
            PurchaseInvoiceLineWork::query()
                ->where('purchase_invoice_work_id', Auth::id())
                ->delete();

            $this->work->update([
                'source_purchase_invoice_id' => null,
                'lines_subtotal' => 0,
                'discount' => 0,
                'amount_paid' => 0,
                'balance' => 0,
                'supplier_id' => null,
                'invoice_date' => null,
                'payment_method_id' => 1,
                'notes' => '',
                'warehouse_id' => $this->defaultWarehouseId(),
            ]);
        }

        $this->headerForm->fill($this->work->toArray());
        $this->lineForm->fill([]);
        $this->storeForm->fill([]);
    }

    public function storeInvoice(): void
    {
        $inventory = app(PurchaseInventoryService::class);

        $this->work->refresh();

        if ($this->work->supplier_id === null) {
            Notification::make()->title('يجب ادخال المورد')->warning()->send();

            return;
        }

        if ($this->work->invoice_date === null) {
            Notification::make()->title('يجب ادخال التاريخ')->warning()->send();

            return;
        }

        if ($this->work->payment_method_id === null) {
            Notification::make()->title('يجب ادخال طريقة الدفع')->warning()->send();

            return;
        }

        $warehouseId = $this->work->warehouse_id ?? $this->defaultWarehouseId();

        if (! $warehouseId) {
            Notification::make()->title('يجب اختيار مخزن التخزين')->warning()->send();

            return;
        }

        $lines = PurchaseInvoiceLineWork::query()
            ->where('purchase_invoice_work_id', Auth::id())
            ->get();

        if ($lines->isEmpty()) {
            Notification::make()->title('لم يتم ادخال اصناف')->warning()->send();

            return;
        }

        if ($lines->contains(fn (PurchaseInvoiceLineWork $line): bool => (float) $line->unit_cost_primary <= 0)) {
            Notification::make()->title('سعر الشراء لا يجوز أن يكون صفر')->warning()->send();

            return;
        }

        $cashBoxId = null;
        $bankAccountId = null;

        if ((float) $this->work->amount_paid > 0) {
            if ((int) $this->work->payment_method_id === 2) {
                $bankAccountId = $this->storeData['bank_account_id'] ?? null;

                if (! $bankAccountId) {
                    Notification::make()->title('يجب اختيار المصرف')->danger()->send();

                    return;
                }
            }

            if ((int) $this->work->payment_method_id === 1) {
                $cashBoxId = $this->storeData['cash_box_id'] ?? null;

                if (! $cashBoxId) {
                    Notification::make()->title('يجب اختيار الخزينة')->danger()->send();

                    return;
                }
            }
        }

        DB::connection($this->work->getConnectionName())->transaction(function () use (
            $inventory,
            $lines,
            $warehouseId,
            $cashBoxId,
            $bankAccountId,
        ): void {
            $invoice = PurchaseInvoice::query()->create([
                'invoice_date' => $this->work->invoice_date,
                'supplier_id' => $this->work->supplier_id,
                'payment_method_id' => $this->work->payment_method_id,
                'warehouse_id' => $warehouseId,
                'lines_subtotal' => $this->work->lines_subtotal,
                'discount' => $this->work->discount,
                'amount_paid' => $this->work->amount_paid,
                'balance' => $this->work->balance,
                'notes' => $this->work->notes,
                'created_by' => Auth::id(),
            ]);

            foreach ($lines as $line) {
                PurchaseInvoiceLine::query()->create([
                    'purchase_invoice_id' => $invoice->id,
                    'item_id' => $line->item_id,
                    'barcode' => $line->barcode,
                    'qty_primary' => $line->qty_primary,
                    'qty_secondary' => $line->qty_secondary,
                    'unit_cost_primary' => $line->unit_cost_primary,
                    'line_cost_total' => $line->line_cost_total,
                    'remaining_qty_primary' => $line->qty_primary,
                    'remaining_qty_secondary' => $line->qty_secondary,
                    'expiry_date' => $line->expiry_date,
                    'created_by' => Auth::id(),
                ]);

                $inventory->applyPurchaseLine(
                    (int) $line->item_id,
                    (int) $warehouseId,
                    (float) $line->qty_primary,
                    (float) $line->qty_secondary,
                    (int) $this->work->payment_method_id,
                    (float) $line->unit_cost_primary,
                    referenceType: PurchaseInvoice::class,
                    referenceId: $invoice->id,
                    movementDate: $this->work->invoice_date,
                    notes: 'فاتورة مشتريات رقم '.(string) $invoice->id,
                );
            }

            if ((float) $this->work->amount_paid > 0) {
                SupplierPayment::query()->create([
                    'payment_date' => $this->work->invoice_date,
                    'supplier_id' => $this->work->supplier_id,
                    'purchase_invoice_id' => $invoice->id,
                    'payment_method_id' => $this->work->payment_method_id,
                    'transaction_kind' => 5,
                    'flow_direction' => 1,
                    'amount' => $this->work->amount_paid,
                    'warehouse_id' => $warehouseId,
                    'cash_box_id' => $cashBoxId,
                    'bank_account_id' => $bankAccountId,
                    'notes' => 'فاتورة مشتريات رقم '.(string) $invoice->id,
                    'created_by' => Auth::id(),
                ]);
            }

            PurchaseInvoiceLineWork::query()
                ->where('purchase_invoice_work_id', Auth::id())
                ->delete();
        });

        $this->work->fill([
            'lines_subtotal' => 0,
            'discount' => 0,
            'amount_paid' => 0,
            'balance' => 0,
            'supplier_id' => null,
            'invoice_date' => null,
            'payment_method_id' => 1,
            'notes' => '',
            'warehouse_id' => $this->defaultWarehouseId(),
        ])->save();

        $this->work->refresh();
        $this->headerForm->fill($this->work->toArray());
        $this->lineForm->fill([]);
        $this->storeForm->fill([]);

        Notification::make()
            ->title('تم تخزين الفاتورة بنجاح')
            ->success()
            ->send();
    }

    public function clearDraft(): void
    {
        $this->work->update([
            'lines_subtotal' => 0,
            'discount' => 0,
            'amount_paid' => 0,
            'balance' => 0,
            'supplier_id' => null,
            'invoice_date' => null,
            'payment_method_id' => 1,
            'notes' => '',
            'source_purchase_invoice_id' => null,
        ]);

        PurchaseInvoiceLineWork::query()
            ->where('purchase_invoice_work_id', Auth::id())
            ->delete();

        $this->work->refresh();
        $this->headerForm->fill($this->work->toArray());
        $this->lineForm->fill([]);

        Notification::make()
            ->title('تم مسح المسودة')
            ->success()
            ->send();
    }
}
