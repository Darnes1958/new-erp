<?php

namespace App\Filament\Market\Support;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLineWork;
use App\Services\Market\SalesInvoicePurchaseDetailsService;
use App\Support\Authz;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class SalesInvoicePurchaseDetailsAction
{
    public static function make(string $name = 'purchase_details'): Action
    {
        return Action::make($name)
            ->label('تفاصيل الشراء')
            ->icon('heroicon-o-clipboard-document-list')
            ->iconButton()
            ->color('warning')
            ->tooltip('تفاصيل الشراء')
            ->visible(fn (): bool => Authz::canViewBuyPrice())
            ->modalHeading('تفاصيل الشراء')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('عودة')
            ->modalContent(function (Model $record): View {
                return view(
                    'filament.market.modals.sales-purchase-details',
                    app(SalesInvoicePurchaseDetailsService::class)->forSalesInvoice(
                        (int) static::invoiceId($record),
                    ),
                );
            });
    }

    public static function forDraft(string $name = 'purchase_details'): Action
    {
        return Action::make($name)
            ->label('تفاصيل الشراء')
            ->icon('heroicon-o-clipboard-document-list')
            ->color('warning')
            ->visible(fn (): bool => Authz::canViewBuyPrice())
            ->disabled(fn (): bool => SalesInvoiceLineWork::query()
                ->where('sales_invoice_work_id', Auth::id())
                ->doesntExist())
            ->modalHeading('تفاصيل الشراء')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('عودة')
            ->modalContent(function ($livewire): View {
                $service = app(SalesInvoicePurchaseDetailsService::class);

                if (
                    is_object($livewire)
                    && method_exists($livewire, 'isEditMode')
                    && $livewire->isEditMode()
                    && property_exists($livewire, 'invoice')
                    && $livewire->invoice instanceof SalesInvoice
                ) {
                    $payload = $service->forSalesInvoice((int) $livewire->invoice->id);
                } else {
                    $warehouseId = 0;

                    if (is_object($livewire) && method_exists($livewire, 'currentWarehouseId')) {
                        $warehouseId = (int) $livewire->currentWarehouseId();
                    }

                    $lines = SalesInvoiceLineWork::query()
                        ->with('item')
                        ->where('sales_invoice_work_id', Auth::id())
                        ->orderBy('id')
                        ->get();

                    $payload = $service->forDraftLines($lines, $warehouseId);
                }

                return view('filament.market.modals.sales-purchase-details', $payload);
            });
    }

    protected static function invoiceId(Model $record): int
    {
        return (int) $record->getKey();
    }
}
