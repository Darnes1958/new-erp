<?php

namespace App\Filament\Market\Pages\InpSell;

use App\Filament\Market\Pages\Concerns\InteractsWithSalesEntry;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\CustomerReceipt;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SalesInvoiceLineWork;
use App\Models\SalesInvoiceWork;
use App\Services\Inventory\SalesInventoryService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InpSell extends Page implements HasSchemas, HasTable
{
    use InteractsWithSalesEntry {
        InteractsWithSalesEntry::table insteadof InteractsWithTable;
    }
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'فاتورة مبيعات جديدة';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->is_prog) {
            return true;
        }

        return $user->can('ادخال مبيعات');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::SalesInvoices;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'inp-sell';

    protected string $view = 'filament.market.pages.inp-sell';

    protected ?string $heading = '';

    public function mount(): void
    {
        $this->initializeWorkDraft();

        if ($this->work->source_sales_invoice_id !== null) {
            SalesInvoiceLineWork::query()
                ->where('sales_invoice_work_id', Auth::id())
                ->delete();

            $this->work->update([
                'source_sales_invoice_id' => null,
                'lines_subtotal' => 0,
                'extra_cost' => 0,
                'rate_markup' => 0,
                'difference_amount' => 0,
                'discount' => 0,
                'grand_total' => 0,
                'amount_paid' => 0,
                'balance' => 0,
                'customer_id' => null,
                'invoice_date' => null,
                'payment_method_id' => 1,
                'notes' => '',
                'warehouse_id' => $this->defaultWarehouseId(),
                'is_retail' => true,
            ]);
        }

        $this->headerForm->fill($this->work->toArray());
        $this->lineForm->fill([]);
        $this->storeForm->fill([]);
    }

    public function storeInvoice(): void
    {
        $inventory = app(SalesInventoryService::class);

        $this->work->refresh();
        $this->refreshPaymentTotals();

        if ($this->work->customer_id === null) {
            Notification::make()->title('يجب ادخال الزبون')->warning()->send();

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

        $warehouseId = $this->currentWarehouseId();

        if (! $warehouseId) {
            Notification::make()->title('يجب اختيار نقطة البيع')->warning()->send();

            return;
        }

        $lines = SalesInvoiceLineWork::query()
            ->where('sales_invoice_work_id', Auth::id())
            ->get();

        if ($lines->isEmpty()) {
            Notification::make()->title('لم يتم ادخال اصناف')->warning()->send();

            return;
        }

        if ($lines->contains(fn (SalesInvoiceLineWork $line): bool => (float) $line->unit_price_primary <= 0)) {
            Notification::make()->title('سعر البيع لا يجوز أن يكون صفر')->warning()->send();

            return;
        }

        foreach ($lines as $line) {
            try {
                $inventory->assertWarehouseStock((int) $line->item_id, $warehouseId, (float) $line->qty_primary);
            } catch (RuntimeException $exception) {
                Notification::make()->title('الصنف '.$line->item_id.': '.$exception->getMessage())->warning()->send();

                return;
            }
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

        try {
            DB::connection($this->work->getConnectionName())->transaction(function () use (
                $inventory,
                $lines,
                $warehouseId,
                $cashBoxId,
                $bankAccountId,
            ): void {
                $invoice = SalesInvoice::query()->create([
                    'invoice_date' => $this->work->invoice_date,
                    'customer_id' => $this->work->customer_id,
                    'payment_method_id' => $this->work->payment_method_id,
                    'warehouse_id' => $warehouseId,
                    'is_retail' => (bool) $this->work->is_retail,
                    'lines_subtotal' => $this->work->lines_subtotal,
                    'extra_cost' => $this->work->extra_cost,
                    'rate_markup' => $this->work->rate_markup,
                    'difference_amount' => $this->work->difference_amount,
                    'discount' => $this->work->discount,
                    'grand_total' => $this->work->grand_total,
                    'amount_paid' => $this->work->amount_paid,
                    'balance' => $this->work->balance,
                    'notes' => $this->work->notes,
                    'created_by' => Auth::id(),
                ]);

                foreach ($lines as $line) {
                    $salesLine = SalesInvoiceLine::query()->create([
                        'sales_invoice_id' => $invoice->id,
                        'item_id' => $line->item_id,
                        'barcode' => $line->barcode,
                        'qty_primary' => $line->qty_primary,
                        'qty_secondary' => $line->qty_secondary,
                        'unit_price_primary' => $line->unit_price_primary,
                        'unit_price_secondary' => $line->unit_price_secondary,
                        'line_total' => $line->line_total,
                        'created_by' => Auth::id(),
                    ]);

                    $profit = $inventory->applySalesLine(
                        (int) $line->item_id,
                        (int) $warehouseId,
                        (float) $line->qty_primary,
                        (float) $line->qty_secondary,
                        (float) $line->unit_price_primary,
                        (int) $invoice->id,
                        (int) $salesLine->id,
                        movementDate: $this->work->invoice_date,
                    );

                    $salesLine->update(['profit' => $profit]);
                }

                if ((float) $this->work->amount_paid > 0) {
                    CustomerReceipt::query()->create([
                        'receipt_date' => $this->work->invoice_date,
                        'customer_id' => $this->work->customer_id,
                        'sales_invoice_id' => $invoice->id,
                        'payment_method_id' => $this->work->payment_method_id,
                        'transaction_kind' => 6,
                        'flow_direction' => 1,
                        'amount' => $this->work->amount_paid,
                        'warehouse_id' => $warehouseId,
                        'cash_box_id' => $cashBoxId,
                        'bank_account_id' => $bankAccountId,
                        'notes' => 'فاتورة مبيعات رقم '.(string) $invoice->id,
                        'created_by' => Auth::id(),
                    ]);
                }

                SalesInvoiceLineWork::query()
                    ->where('sales_invoice_work_id', Auth::id())
                    ->delete();
            });
        } catch (RuntimeException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        $this->work->fill([
            'lines_subtotal' => 0,
            'extra_cost' => 0,
            'rate_markup' => 0,
            'difference_amount' => 0,
            'discount' => 0,
            'grand_total' => 0,
            'amount_paid' => 0,
            'balance' => 0,
            'customer_id' => null,
            'invoice_date' => null,
            'payment_method_id' => 1,
            'notes' => '',
            'warehouse_id' => $this->defaultWarehouseId(),
            'is_retail' => true,
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
            'extra_cost' => 0,
            'rate_markup' => 0,
            'difference_amount' => 0,
            'discount' => 0,
            'grand_total' => 0,
            'amount_paid' => 0,
            'balance' => 0,
            'customer_id' => null,
            'invoice_date' => null,
            'payment_method_id' => 1,
            'notes' => '',
            'source_sales_invoice_id' => null,
        ]);

        SalesInvoiceLineWork::query()
            ->where('sales_invoice_work_id', Auth::id())
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
