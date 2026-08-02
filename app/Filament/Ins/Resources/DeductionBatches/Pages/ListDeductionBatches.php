<?php

namespace App\Filament\Ins\Resources\DeductionBatches\Pages;

use App\Filament\Ins\Resources\DeductionBatches\DeductionBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeductionBatches extends ListRecords
{
    protected static string $resource = DeductionBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('حافظة جديدة'),
        ];
    }
}
