<?php

namespace App\Filament\Finance\Resources\ExpenseTypes\Pages;

use App\Filament\Finance\Resources\ExpenseTypes\ExpenseTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExpenseType extends EditRecord
{
    protected static string $resource = ExpenseTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (): bool => $this->record->expenses()->exists()),
        ];
    }
}
