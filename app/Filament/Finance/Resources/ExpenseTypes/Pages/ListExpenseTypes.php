<?php

namespace App\Filament\Finance\Resources\ExpenseTypes\Pages;

use App\Filament\Finance\Resources\ExpenseTypes\ExpenseTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExpenseTypes extends ListRecords
{
    protected static string $resource = ExpenseTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
