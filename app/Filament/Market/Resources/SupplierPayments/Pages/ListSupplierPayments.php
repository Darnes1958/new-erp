<?php

namespace App\Filament\Market\Resources\SupplierPayments\Pages;

use App\Filament\Market\Resources\SupplierPayments\SupplierPaymentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListSupplierPayments extends ListRecords
{
    protected static string $resource = SupplierPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => Auth::user()?->can('ادخال ايصالات موردين') || Auth::user()?->is_prog),
        ];
    }
}
