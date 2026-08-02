<?php

namespace App\Filament\Market\Pages\QuickSell\Schemas;

use App\Models\CashBox;
use App\Models\SalesInvoiceLineWork;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class QuickSalesStoreForm
{
    public static function configure(Schema $schema, object $page): Schema
    {
        return $schema
            ->statePath('storeData')
            ->columns(1)
            ->components([
                Select::make('cash_box_id')
                    ->options(fn () => CashBox::query()->where('is_active', true)->pluck('name', 'id'))
                    ->label('الخزينة')
                    ->columnSpanFull()
                    ->searchable()
                    ->default(fn () => $page->defaultCashBoxId())
                    ->disabled(fn (): bool => $page->hasAssignedCashBox())
                    ->visible(fn () => (int) ($page->work->payment_method_id ?? 1) === 1),
                Actions::make([
                    Action::make('store')
                        ->label('تخزين')
                        ->icon('heroicon-m-plus')
                        ->button()
                        ->visible(fn (): bool => SalesInvoiceLineWork::query()
                            ->where('sales_invoice_work_id', Auth::id())
                            ->exists())
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn () => $page->storeInvoice()),
                    Action::make('clear')
                        ->label('مسح')
                        ->icon('heroicon-m-trash')
                        ->button()
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn () => $page->clearDraft()),
                    Action::make('print')
                        ->label('طباعة')
                        ->icon('heroicon-o-printer')
                        ->button()
                        ->color('info')
                        ->visible(fn (): bool => filled($page->idToPrint ?? null))
                        ->url(fn (): string => route('pdf.sales-invoice', ['salesInvoice' => $page->idToPrint]))
                        ->openUrlInNewTab(),
                ])
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'items-center justify-between']),
            ]);
    }
}
