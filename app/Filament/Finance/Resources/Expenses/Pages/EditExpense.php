<?php

namespace App\Filament\Finance\Resources\Expenses\Pages;

use App\Filament\Finance\Resources\Expenses\ExpenseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected ?string $heading = 'تعديل مصروفات';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => Auth::user()?->is_prog || Auth::user()?->can('الغاء مصروفات')),
        ];
    }
}
