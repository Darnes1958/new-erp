<?php

namespace App\Filament\Market\Resources\SalesInvoices\Pages;

use App\Filament\Market\Pages\Concerns\InteractsWithSalesEntry;
use App\Filament\Market\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\CustomerReceipt;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLineWork;
use App\Services\Inventory\SalesInvoiceUpdateService;
use App\Support\ProgrammingError;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\Auth;
use Throwable;

class EditSell extends Page implements HasSchemas, HasTable
{
    use InteractsWithSalesEntry {
        InteractsWithSalesEntry::table insteadof InteractsWithTable;
    }
    use InteractsWithRecord;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static string $resource = SalesInvoiceResource::class;

    protected static ?string $navigationLabel = 'تعديل فاتورة مبيعات';

    protected static ?string $slug = 'edit-sell';

    protected string $view = 'filament.market.pages.inp-sell';

    public SalesInvoice $invoice;

    public static function canAccess(array $parameters = []): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->is_prog) {
            return true;
        }

        return $user->can('تعديل مبيعات');
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public function isEditMode(): bool
    {
        return true;
    }

    public function getHeading(): string
    {
        return 'تعديل فاتورة مبيعات رقم '.(string) $this->invoice->id;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->invoice = $this->record;

        $this->initializeWorkDraft();
        $this->loadInvoiceIntoWork();
    }

    protected function loadInvoiceIntoWork(): void
    {
        SalesInvoiceLineWork::query()
            ->where('sales_invoice_work_id', Auth::id())
            ->delete();

        $this->invoice->load('lines');

        $entryReceipt = CustomerReceipt::query()
            ->where('sales_invoice_id', $this->invoice->id)
            ->where('transaction_kind', 6)
            ->first();

        $entryPaid = (float) ($entryReceipt?->amount ?? $this->invoice->amount_paid);

        $this->work->update([
            'source_sales_invoice_id' => $this->invoice->id,
            'invoice_date' => $this->invoice->invoice_date,
            'customer_id' => $this->invoice->customer_id,
            'payment_method_id' => $this->invoice->payment_method_id,
            'warehouse_id' => $this->invoice->warehouse_id,
            'is_retail' => $this->invoice->is_retail,
            'lines_subtotal' => $this->invoice->lines_subtotal,
            'extra_cost' => $this->invoice->extra_cost,
            'rate_markup' => $this->invoice->rate_markup,
            'difference_amount' => $this->invoice->difference_amount,
            'discount' => $this->invoice->discount,
            'grand_total' => $this->invoice->grand_total,
            'amount_paid' => $entryPaid,
            'balance' => (float) $this->invoice->grand_total - $entryPaid,
            'notes' => $this->invoice->notes,
        ]);

        foreach ($this->invoice->lines as $line) {
            SalesInvoiceLineWork::query()->create([
                'sales_invoice_work_id' => Auth::id(),
                'source_sales_invoice_line_id' => $line->id,
                'item_id' => $line->item_id,
                'barcode' => $line->barcode,
                'qty_primary' => $line->qty_primary,
                'qty_secondary' => $line->qty_secondary,
                'unit_price_primary' => $line->unit_price_primary,
                'unit_price_secondary' => $line->unit_price_secondary,
                'line_total' => $line->line_total,
                'created_by' => Auth::id(),
            ]);
        }

        $this->headerForm->fill($this->work->toArray());
        $this->lineForm->fill([]);
        $this->storeForm->fill([
            'cash_box_id' => $entryReceipt?->cash_box_id,
            'bank_account_id' => $entryReceipt?->bank_account_id,
        ]);
    }

    public function storeInvoice(): void
    {
        $this->updateInvoice();
    }

    protected function updateInvoice(): void
    {
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

        $lines = SalesInvoiceLineWork::query()
            ->where('sales_invoice_work_id', Auth::id())
            ->get();

        if (! $this->assertWorkHasAtLeastOneLine()) {
            return;
        }

        try {
            $error = app(SalesInvoiceUpdateService::class)->updateFromWork(
                $this->invoice->fresh(),
                $this->work,
                $lines,
                $cashBoxId,
                $bankAccountId,
            );
        } catch (Throwable $exception) {
            ProgrammingError::notify($exception);

            return;
        }

        if ($error !== null) {
            Notification::make()->title($error)->warning()->send();

            return;
        }

        Notification::make()
            ->title('تم حفظ التعديلات بنجاح')
            ->success()
            ->send();

        $this->redirect(SalesInvoiceResource::getUrl('index'));
    }

    public function clearDraft(): void
    {
        $this->cancelEdit();
    }

    protected function cancelEdit(): void
    {
        $this->redirect(SalesInvoiceResource::getUrl('index'));
    }
}
