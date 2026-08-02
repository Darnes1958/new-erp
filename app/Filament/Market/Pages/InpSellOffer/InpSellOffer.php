<?php

namespace App\Filament\Market\Pages\InpSellOffer;

use App\Filament\Market\Pages\Concerns\InteractsWithSalesOfferEntry;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\ItemPrice;
use App\Models\SalesOfferInvoice;
use App\Models\SalesOfferInvoiceLine;
use App\Models\SalesOfferInvoiceLineWork;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\Auth;

class InpSellOffer extends Page implements HasSchemas, HasTable
{
    use InteractsWithSalesOfferEntry {
        InteractsWithSalesOfferEntry::table insteadof InteractsWithTable;
    }
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'فاتورة عرض جديدة';

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

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'inp-sell-offer';

    protected string $view = 'filament.market.pages.inp-sell';

    protected ?string $heading = '';

    public ?int $idToPrint = null;

    public function mount(): void
    {
        $this->initializeOfferWorkDraft();

        if ($this->work->source_sales_offer_invoice_id !== null) {
            SalesOfferInvoiceLineWork::query()
                ->where('sales_offer_invoice_work_id', Auth::id())
                ->delete();

            $this->work->update([
                'source_sales_offer_invoice_id' => null,
                'lines_subtotal' => 0,
                'extra_cost' => 0,
                'rate_markup' => 0,
                'difference_amount' => 0,
                'grand_total' => 0,
                'customer_id' => null,
                'invoice_date' => null,
                'payment_method_id' => null,
                'notes' => null,
                'warehouse_id' => $this->defaultWarehouseId(),
                'is_retail' => true,
            ]);
        }

        $this->headerForm->fill($this->work->toArray());
        $this->lineForm->fill([]);
        $this->storeForm->fill([]);
    }

    public function storeOffer(): void
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

        $invoice = SalesOfferInvoice::query()->create([
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
            'created_by' => Auth::id(),
        ]);

        foreach ($lines as $line) {
            SalesOfferInvoiceLine::query()->create([
                'sales_offer_invoice_id' => $invoice->id,
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

        SalesOfferInvoiceLineWork::query()
            ->where('sales_offer_invoice_work_id', Auth::id())
            ->delete();

        $this->idToPrint = (int) $invoice->id;

        $this->work->update([
            'lines_subtotal' => 0,
            'extra_cost' => 0,
            'rate_markup' => 0,
            'difference_amount' => 0,
            'grand_total' => 0,
            'customer_id' => null,
            'invoice_date' => null,
            'payment_method_id' => null,
            'notes' => null,
            'warehouse_id' => $this->defaultWarehouseId(),
            'is_retail' => true,
        ]);

        $this->work->refresh();
        $this->headerForm->fill($this->work->toArray());
        $this->lineForm->fill([]);
        $this->storeForm->fill([]);

        Notification::make()
            ->title('تم تخزين فاتورة العرض بنجاح')
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
            'grand_total' => 0,
            'customer_id' => null,
            'invoice_date' => null,
            'payment_method_id' => null,
            'notes' => null,
        ]);

        SalesOfferInvoiceLineWork::query()
            ->where('sales_offer_invoice_work_id', Auth::id())
            ->delete();

        $this->idToPrint = null;
        $this->work->refresh();
        $this->headerForm->fill($this->work->toArray());
        $this->lineForm->fill([]);

        Notification::make()
            ->title('تم مسح المسودة')
            ->success()
            ->send();
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
