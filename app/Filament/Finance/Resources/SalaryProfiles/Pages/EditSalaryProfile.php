<?php

namespace App\Filament\Finance\Resources\SalaryProfiles\Pages;

use App\Filament\Finance\Resources\SalaryProfiles\SalaryProfileResource;
use App\Models\SalaryTransaction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSalaryProfile extends EditRecord
{
    protected static string $resource = SalaryProfileResource::class;

    protected ?string $heading = 'تعديل بيانات مرتب';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('إلغاء')
                ->modalHeading('الغاء المرتب')
                ->hidden(fn (): bool => SalaryTransaction::query()
                    ->where('salary_profile_id', $this->record->id)
                    ->exists()),
        ];
    }
}
