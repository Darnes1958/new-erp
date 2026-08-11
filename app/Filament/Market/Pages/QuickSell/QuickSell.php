<?php

namespace App\Filament\Market\Pages\QuickSell;

use App\Filament\Market\Pages\Concerns\InteractsWithQuickSalesEntry;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\CustomerReceipt;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SalesInvoiceLineWork;
use App\Support\ProgrammingError;
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
use Throwable;

class QuickSell extends Page implements HasSchemas, HasTable
{
    use InteractsWithQuickSalesEntry {
        InteractsWithQuickSalesEntry::table insteadof InteractsWithTable;
    }
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'مبيعات سريعة';

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
        return static::canAccess();
    }

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::SalesInvoices;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'quick-sell';

    protected string $view = 'filament.market.pages.inp-sell';

    protected ?string $heading = '';

    public ?int $idToPrint = null;

    public function mount(): void
    {
        $this->initializeWorkDraft();
        $this->applyQuickDefaults();

        $this->headerForm->fill($this->work->toArray());
        $this->lineForm->fill(['qty_primary' => 1]);
        $this->storeForm->fill(['cash_box_id' => $this->defaultCashBoxId()]);
        $this->refreshPaymentTotals();
    }

    public function storeInvoice(): void
    {
        $inventory = app(SalesInventoryService::class);

        $this->work->refresh();
        $this->refreshPaymentTotals();

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
            Notification::make()->title('يجب أن تحتوي الفاتورة على صنف واحد على الأقل')->warning()->send();

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

        if ((float) $this->work->amount_paid > 0 && (int) $this->work->payment_method_id === 1) {
            $cashBoxId = $this->storeData['cash_box_id'] ?? $this->defaultCashBoxId();

            if (! $cashBoxId) {
                Notification::make()->title('يجب اختيار الخزينة')->danger()->send();

                return;
            }
        }

        try {
            $invoiceId = DB::connection($this->work->getConnectionName())->transaction(function () use (
                $inventory,
                $lines,
                $warehouseId,
                $cashBoxId,
            ): int {
                $invoice = SalesInvoice::query()->create([
                    'invoice_date' => $this->work->invoice_date,
                    'customer_id' => 1,
                    'payment_method_id' => $this->work->payment_method_id,
                    'warehouse_id' => $warehouseId,
                    'is_retail' => true,
                    'lines_subtotal' => $this->work->lines_subtotal,
                    'extra_cost' => 0,
                    'rate_markup' => 0,
                    'difference_amount' => 0,
                    'discount' => 0,
                    'grand_total' => $this->work->grand_total,
                    'amount_paid' => $this->work->amount_paid,
                    'balance' => 0,
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
                        'customer_id' => 1,
                        'sales_invoice_id' => $invoice->id,
                        'payment_method_id' => $this->work->payment_method_id,
                        'transaction_kind' => 6,
                        'flow_direction' => 1,
                        'amount' => $this->work->amount_paid,
                        'warehouse_id' => $warehouseId,
                        'cash_box_id' => $cashBoxId,
                        'bank_account_id' => null,
                        'notes' => 'فاتورة مبيعات رقم '.(string) $invoice->id,
                        'created_by' => Auth::id(),
                    ]);
                }

                SalesInvoiceLineWork::query()
                    ->where('sales_invoice_work_id', Auth::id())
                    ->delete();

                return (int) $invoice->id;
            });
        } catch (Throwable $exception) {
            ProgrammingError::notify($exception);

            return;
        }

        $this->idToPrint = $invoiceId;
        $this->work->refresh();
        $this->applyQuickDefaults(clearLines: false);
        $this->work->update([
            'lines_subtotal' => 0,
            'grand_total' => 0,
            'amount_paid' => 0,
            'balance' => 0,
            'notes' => null,
        ]);

        $this->headerForm->fill($this->work->toArray());
        $this->lineForm->fill(['qty_primary' => 1]);
        $this->storeForm->fill(['cash_box_id' => $this->defaultCashBoxId()]);

        Notification::make()
            ->title('تم تخزين الفاتورة بنجاح')
            ->success()
            ->send();
    }

    public function clearDraft(): void
    {
        SalesInvoiceLineWork::query()
            ->where('sales_invoice_work_id', Auth::id())
            ->delete();

        $this->work->update([
            'lines_subtotal' => 0,
            'grand_total' => 0,
            'amount_paid' => 0,
            'balance' => 0,
            'notes' => null,
            'source_sales_invoice_id' => null,
        ]);

        $this->applyQuickDefaults();
        $this->idToPrint = null;
        $this->work->refresh();
        $this->headerForm->fill($this->work->toArray());
        $this->lineForm->fill(['qty_primary' => 1]);
        $this->storeForm->fill(['cash_box_id' => $this->defaultCashBoxId()]);

        Notification::make()
            ->title('تم مسح المسودة')
            ->success()
            ->send();
    }

    protected function applyQuickDefaults(bool $clearLines = false): void
    {
        if ($clearLines) {
            SalesInvoiceLineWork::query()
                ->where('sales_invoice_work_id', Auth::id())
                ->delete();
        }

        $this->work->fill([
            'customer_id' => 1,
            'invoice_date' => now()->toDateString(),
            'payment_method_id' => 1,
            'warehouse_id' => $this->work->warehouse_id ?: $this->defaultWarehouseId(),
            'is_retail' => true,
        ]);
        $this->work->save();
    }
}
