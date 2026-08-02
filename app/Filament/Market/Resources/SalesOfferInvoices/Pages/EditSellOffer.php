<?php

namespace App\Filament\Market\Resources\SalesOfferInvoices\Pages;

use App\Filament\Market\Pages\Concerns\InteractsWithSalesOfferEntry;
use App\Filament\Market\Resources\SalesOfferInvoices\SalesOfferInvoiceResource;
use App\Models\ItemPrice;
use App\Models\SalesOfferInvoice;
use App\Models\SalesOfferInvoiceLine;
use App\Models\SalesOfferInvoiceLineWork;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\Auth;

class EditSellOffer extends Page implements HasSchemas, HasTable
{
    use InteractsWithSalesOfferEntry {
        InteractsWithSalesOfferEntry::table insteadof InteractsWithTable;
    }
    use InteractsWithRecord;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static string $resource = SalesOfferInvoiceResource::class;

    protected static ?string $navigationLabel = 'تعديل فاتورة عرض';

    protected static ?string $slug = 'edit-sell-offer';

    protected string $view = 'filament.market.pages.inp-sell';

    public SalesOfferInvoice $offer;

    public ?int $idToPrint = null;

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
        return 'تعديل فاتورة عرض رقم '.(string) $this->offer->id;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->offer = $this->record;
        $this->idToPrint = (int) $this->offer->id;

        $this->initializeOfferWorkDraft();
        $this->loadOfferIntoWork();
    }

    protected function loadOfferIntoWork(): void
    {
        SalesOfferInvoiceLineWork::query()
            ->where('sales_offer_invoice_work_id', Auth::id())
            ->delete();

        $this->offer->load('lines');

        $this->work->update([
            'source_sales_offer_invoice_id' => $this->offer->id,
            'invoice_date' => $this->offer->invoice_date,
            'customer_id' => $this->offer->customer_id,
            'payment_method_id' => $this->offer->payment_method_id,
            'warehouse_id' => $this->offer->warehouse_id,
            'is_retail' => $this->offer->is_retail,
            'lines_subtotal' => $this->offer->lines_subtotal,
            'extra_cost' => $this->offer->extra_cost,
            'rate_markup' => $this->offer->rate_markup,
            'difference_amount' => $this->offer->difference_amount,
            'grand_total' => $this->offer->grand_total,
            'notes' => $this->offer->notes,
        ]);

        foreach ($this->offer->lines as $line) {
            SalesOfferInvoiceLineWork::query()->create([
                'sales_offer_invoice_work_id' => Auth::id(),
                'source_sales_offer_invoice_line_id' => $line->id,
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
        $this->storeForm->fill([]);
    }

    public function storeOffer(): void
    {
        $this->updateOffer();
    }

    protected function updateOffer(): void
    {
        $this->work->refresh();
        $this->refreshOfferTotals();

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

        $lines = SalesOfferInvoiceLineWork::query()
            ->where('sales_offer_invoice_work_id', Auth::id())
            ->get();

        if ($lines->isEmpty()) {
            Notification::make()->title('لم يتم ادخال اصناف')->warning()->send();

            return;
        }

        if ($lines->contains(fn (SalesOfferInvoiceLineWork $line): bool => (float) $line->unit_price_primary <= 0)) {
            Notification::make()->title('سعر البيع لا يجوز أن يكون صفر')->warning()->send();

            return;
        }

        $this->offer->update([
            'invoice_date' => $this->work->invoice_date,
            'customer_id' => $this->work->customer_id,
            'payment_method_id' => $this->work->payment_method_id,
            'warehouse_id' => $warehouseId,
            'is_retail' => (bool) $this->work->is_retail,
            'lines_subtotal' => $this->work->lines_subtotal,
            'extra_cost' => $this->work->extra_cost,
            'rate_markup' => $this->work->rate_markup,
            'difference_amount' => $this->work->difference_amount,
            'grand_total' => $this->work->grand_total,
            'notes' => $this->work->notes,
        ]);

        $this->offer->lines()->delete();

        foreach ($lines as $line) {
            SalesOfferInvoiceLine::query()->create([
                'sales_offer_invoice_id' => $this->offer->id,
                'item_id' => $line->item_id,
                'barcode' => $line->barcode,
                'qty_primary' => $line->qty_primary,
                'qty_secondary' => $line->qty_secondary,
                'unit_price_primary' => $line->unit_price_primary,
                'unit_price_secondary' => $line->unit_price_secondary,
                'line_total' => $line->line_total,
                'created_by' => Auth::id(),
            ]);

            $this->seedItemPriceIfMissing(
                (int) $line->item_id,
                (int) $this->work->payment_method_id,
                (float) $line->unit_price_primary,
                (float) $line->unit_price_secondary,
            );
        }

        $this->offer->refresh();
        $this->loadOfferIntoWork();

        Notification::make()
            ->title('تم حفظ التعديلات بنجاح')
            ->success()
            ->send();
    }

    public function clearDraft(): void
    {
        $this->cancelEdit();
    }

    protected function cancelEdit(): void
    {
        $this->redirect(SalesOfferInvoiceResource::getUrl('index'));
    }

    protected function seedItemPriceIfMissing(
        int $itemId,
        int $paymentMethodId,
        float $pricePrimary,
        float $priceSecondary,
    ): void {
        $exists = ItemPrice::query()
            ->where('item_id', $itemId)
            ->where('payment_method_id', $paymentMethodId)
            ->where('price_kind', 'sell')
            ->exists();

        if ($exists) {
            return;
        }

        ItemPrice::query()->create([
            'item_id' => $itemId,
            'payment_method_id' => $paymentMethodId,
            'price_kind' => 'sell',
            'price_primary' => $pricePrimary,
            'price_secondary' => $priceSecondary,
        ]);
    }
}
