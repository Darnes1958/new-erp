<?php

namespace App\Filament\Market\Pages\InpSellOffer\Schemas;

use App\Models\SalesOfferInvoiceLineWork;
use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class SalesOfferStoreForm
{
    public static function configure(Schema $schema, object $page): Schema
    {
        return $schema
            ->statePath('storeData')
            ->columns(1)
            ->components([
                Actions::make([
                    Action::make('store')
                        ->label(fn () => $page->isEditMode() ? 'حفظ التعديلات' : 'تخزين')
                        ->icon(fn () => $page->isEditMode() ? 'heroicon-m-check' : 'heroicon-m-plus')
                        ->button()
                        ->visible(fn (): bool => SalesOfferInvoiceLineWork::query()
                            ->where('sales_offer_invoice_work_id', Auth::id())
                            ->exists())
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn () => $page->storeOffer()),
                    Action::make('clear')
                        ->label(fn () => $page->isEditMode() ? 'إلغاء' : 'مسح')
                        ->icon(fn () => $page->isEditMode() ? 'heroicon-m-x-mark' : 'heroicon-m-trash')
                        ->button()
                        ->color('danger')
                        ->requiresConfirmation(fn () => ! $page->isEditMode())
                        ->action(fn () => $page->clearDraft()),
                    Action::make('print')
                        ->label('طباعة')
                        ->icon('heroicon-o-printer')
                        ->button()
                        ->color('info')
                        ->visible(fn (): bool => filled($page->idToPrint ?? null))
                        ->url(fn (): string => route('pdf.sales-offer-invoice', ['salesOfferInvoice' => $page->idToPrint]))
                        ->openUrlInNewTab(),
                ])
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'items-center justify-between']),
            ]);
    }
}
