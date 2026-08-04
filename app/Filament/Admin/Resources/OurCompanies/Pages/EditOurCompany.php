<?php

namespace App\Filament\Admin\Resources\OurCompanies\Pages;

use App\Filament\Admin\Resources\OurCompanies\OurCompanyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditOurCompany extends EditRecord
{
    protected static string $resource = OurCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return Auth::user()?->is_prog
            ? [DeleteAction::make()]
            : [];
    }
}
