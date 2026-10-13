<?php

namespace App\Filament\Market\Support;

use App\Filament\Market\Resources\SalesInvoices\Schemas\SalesInvoiceInfolist;
use App\Models\CustomerLedgerEntry;
use App\Models\SalesInvoice;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

final class SalesInvoiceViewModalAction
{
    public static function make(string $name = 'view'): Action
    {
        return Action::make($name)
            ->label('عرض')
            ->icon('heroicon-o-eye')
            ->iconButton()
            ->color('gray')
            ->tooltip('عرض')
            ->modalHeading(fn (Model $record): string => 'فاتورة بيع رقم '.static::invoiceId($record))
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق')
            ->disabledSchema()
            ->schema(fn (Schema $schema): Schema => SalesInvoiceInfolist::configure($schema))
            ->mountUsing(function (?Schema $schema, Model $record): void {
                $invoice = static::invoice($record);

                $schema?->record($invoice)->model($invoice)->fill();
            });
    }

    protected static function invoice(Model $record): ?SalesInvoice
    {
        if ($record instanceof SalesInvoice) {
            return $record->loadMissing(['customer', 'paymentMethod', 'warehouse', 'lines.item']);
        }

        return SalesInvoice::query()
            ->with(['customer', 'paymentMethod', 'warehouse', 'lines.item'])
            ->find(static::invoiceId($record));
    }

    protected static function invoiceId(Model $record): int|string
    {
        if ($record instanceof CustomerLedgerEntry) {
            return $record->id;
        }

        return $record->getKey();
    }
}
