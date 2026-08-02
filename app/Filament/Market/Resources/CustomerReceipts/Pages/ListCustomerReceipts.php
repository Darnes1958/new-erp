<?php

namespace App\Filament\Market\Resources\CustomerReceipts\Pages;

use App\Filament\Market\Resources\CustomerReceipts\CustomerReceiptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListCustomerReceipts extends ListRecords
{
    protected static string $resource = CustomerReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => Auth::user()?->can('ادخال ايصالات زبائن') || Auth::user()?->is_prog),
        ];
    }
}
