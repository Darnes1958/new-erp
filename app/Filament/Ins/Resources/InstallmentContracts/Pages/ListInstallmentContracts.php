<?php

namespace App\Filament\Ins\Resources\InstallmentContracts\Pages;

use App\Filament\Ins\Resources\InstallmentContracts\InstallmentContractResource;
use Filament\Resources\Pages\ListRecords;

class ListInstallmentContracts extends ListRecords
{
    protected static string $resource = InstallmentContractResource::class;
}
