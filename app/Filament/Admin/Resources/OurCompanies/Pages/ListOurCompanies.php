<?php

namespace App\Filament\Admin\Resources\OurCompanies\Pages;

use App\Filament\Admin\Resources\OurCompanies\OurCompanyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListOurCompanies extends ListRecords
{
    protected static string $resource = OurCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return Auth::user()?->is_prog
            ? [CreateAction::make()]
            : [];
    }
}
