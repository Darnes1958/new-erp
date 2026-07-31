<?php

namespace App\Filament\Market\Pages\InpBuy\Schemas;

use App\Models\BankAccount;
use App\Models\CashBox;
use App\Models\PurchaseInvoiceLineWork;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class PurchaseStoreForm
{
    public static function configure(Schema $schema, object $page): Schema
    {
        return $schema
            ->statePath('storeData')
            ->columns(1)
            ->components([
                Select::make('bank_account_id')
                    ->options(fn () => BankAccount::query()->where('is_active', true)->pluck('name', 'id'))
                    ->label('المصرف')
                    ->columnSpanFull()
                    ->searchable()
                    ->visible(function () use ($page): bool {
                        return $page->work->amount_paid > 0 && $page->work->payment_method_id == 2;
                    }),
                Select::make('cash_box_id')
                    ->options(fn () => CashBox::query()->where('is_active', true)->pluck('name', 'id'))
                    ->label('الخزينة')
                    ->columnSpanFull()
                    ->searchable()
                    ->visible(function () use ($page): bool {
                        return $page->work->amount_paid > 0 && $page->work->payment_method_id == 1;
                    }),
                Actions::make([
                    Action::make('store')
                        ->label(fn () => $page->isEditMode() ? 'حفظ التعديلات' : 'تخزين')
                        ->icon(fn () => $page->isEditMode() ? 'heroicon-m-check' : 'heroicon-m-plus')
                        ->button()
                        ->visible(fn (): bool => PurchaseInvoiceLineWork::query()
                            ->where('purchase_invoice_work_id', Auth::id())
                            ->exists())
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn () => $page->storeInvoice()),
                    Action::make('clear')
                        ->label(fn () => $page->isEditMode() ? 'إلغاء' : 'مسح')
                        ->icon(fn () => $page->isEditMode() ? 'heroicon-m-x-mark' : 'heroicon-m-trash')
                        ->button()
                        ->color('danger')
                        ->requiresConfirmation(fn () => ! $page->isEditMode())
                        ->action(fn () => $page->clearDraft()),
                ])
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'items-center justify-between']),
            ]);
    }
}
