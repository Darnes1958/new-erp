<?php

namespace App\Filament\Ins\Resources\InstallmentCancelledContracts\Pages;

use App\Filament\Ins\Resources\InstallmentCancelledContracts\InstallmentCancelledContractResource;
use Filament\Resources\Pages\ListRecords;

class ListInstallmentCancelledContracts extends ListRecords
{
    protected static string $resource = InstallmentCancelledContractResource::class;

    protected ?string $heading = 'عقود ملغية بعد التعاقد';
}
