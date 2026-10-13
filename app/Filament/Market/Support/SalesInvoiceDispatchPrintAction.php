<?php

namespace App\Filament\Market\Support;

use Filament\Actions\Action;

final class SalesInvoiceDispatchPrintAction
{
    public static function make(string $name = 'printDispatch', bool $iconButton = true): Action
    {
        $action = Action::make($name)
            ->label('إذن صرف')
            ->icon('heroicon-o-archive-box')
            ->color('warning')
            ->tooltip('إذن صرف بضاعة')
            ->url(fn ($record): string => route('pdf.sales-dispatch-note', ['salesInvoice' => $record]))
            ->openUrlInNewTab();

        return $iconButton ? $action->iconButton() : $action;
    }
}
