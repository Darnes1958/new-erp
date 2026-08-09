<?php

namespace App\Filament\Admin\Resources\SystemOperationLogs\Pages;

use App\Filament\Admin\Resources\SystemOperationLogs\SystemOperationLogResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSystemOperationLogs extends ListRecords
{
    protected static string $resource = SystemOperationLogResource::class;

    protected static ?string $title = 'مراقبة عمليات التعديل والإلغاء';

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->with(['customer', 'item', 'user']);
    }
}
