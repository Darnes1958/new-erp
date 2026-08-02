<?php

namespace App\Filament\Market\Resources\PurchaseInvoices\Pages;

use App\Filament\Market\Pages\Concerns\InteractsWithPurchaseEntry;
use App\Filament\Market\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLineWork;
use App\Models\SupplierPayment;
use App\Services\Inventory\PurchaseInvoiceUpdateService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\Auth;

class EditBuy extends Page implements HasSchemas, HasTable
{
    use InteractsWithPurchaseEntry {
        InteractsWithPurchaseEntry::table insteadof InteractsWithTable;
    }
    use InteractsWithRecord;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static string $resource = PurchaseInvoiceResource::class;

    protected static ?string $navigationLabel = 'تعديل فاتورة شراء';

    protected static ?string $slug = 'edit-buy';

    protected string $view = 'filament.market.pages.inp-buy';

    public PurchaseInvoice $invoice;

    public static function canAccess(array $parameters = []): bool
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
        return 'تعديل فاتورة مشتريات رقم '.(string) $this->invoice->id;
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
        PurchaseInvoiceLineWork::query()
            ->where('purchase_invoice_work_id', Auth::id())
            ->delete();

        $this->invoice->load('lines');

        $entryPayment = SupplierPayment::query()
            ->where('purchase_invoice_id', $this->invoice->id)
            ->where('transaction_kind', 5)
            ->first();

        $entryPaid = (float) ($entryPayment?->amount ?? $this->invoice->amount_paid);
        $discount = (float) $this->invoice->discount;
        $subtotal = (float) $this->invoice->lines->sum('line_cost_total');

        $this->work->update([
            'source_purchase_invoice_id' => $this->invoice->id,
            'invoice_date' => $this->invoice->invoice_date,
            'supplier_id' => $this->invoice->supplier_id,
            'payment_method_id' => $this->invoice->payment_method_id,
            'warehouse_id' => $this->invoice->warehouse_id,
            'lines_subtotal' => $subtotal,
            'discount' => $discount,
            'amount_paid' => $entryPaid,
            'balance' => $this->calculateDisplayBalance($subtotal, $entryPaid, $discount),
            'notes' => $this->invoice->notes,
        ]);

        foreach ($this->invoice->lines as $line) {
            PurchaseInvoiceLineWork::query()->create([
                'purchase_invoice_work_id' => Auth::id(),
                'source_purchase_invoice_line_id' => $line->id,
                'item_id' => $line->item_id,
                'barcode' => $line->barcode,
                'qty_primary' => $line->qty_primary,
                'qty_secondary' => $line->qty_secondary,
                'unit_cost_primary' => $line->unit_cost_primary,
                'line_cost_total' => $line->line_cost_total,
                'expiry_date' => $line->expiry_date,
                'created_by' => Auth::id(),
            ]);
        }

        $this->headerForm->fill($this->work->toArray());
        $this->lineForm->fill([]);
        $this->storeForm->fill([
            'cash_box_id' => $entryPayment?->cash_box_id,
            'bank_account_id' => $entryPayment?->bank_account_id,
        ]);
    }

    public function storeInvoice(): void
    {
        $this->updateInvoice();
    }

    public function updateInvoice(): void
    {
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

        $lines = PurchaseInvoiceLineWork::query()
            ->where('purchase_invoice_work_id', Auth::id())
            ->get();

        $error = app(PurchaseInvoiceUpdateService::class)->updateFromWork(
            $this->invoice->fresh(),
            $this->work,
            $lines,
            $cashBoxId,
            $bankAccountId,
        );

        if ($error !== null) {
            Notification::make()->title($error)->warning()->send();

            return;
        }

        Notification::make()
            ->title('تم حفظ التعديلات بنجاح')
            ->success()
            ->send();

        $this->redirect(PurchaseInvoiceResource::getUrl('index'));
    }

    public function clearDraft(): void
    {
        $this->cancelEdit();
    }

    public function cancelEdit(): void
    {
        $this->redirect(PurchaseInvoiceResource::getUrl('index'));
    }
}
