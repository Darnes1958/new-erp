<?php

namespace App\Filament\Market\Widgets\DailyMovement;

use App\Filament\Market\Widgets\DailyMovement\Concerns\InteractsWithDailyMovementFilters;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

abstract class BaseDailyMovementTableWidget extends TableWidget
{
    use InteractsWithDailyMovementFilters;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected function sectionHeading(string $title, string $colorClass = 'text-primary-600'): HtmlString
    {
        return new HtmlString('<div class="'.$colorClass.' text-lg">'.$title.'</div>');
    }

    public function getTableRecordKey(Model | array $record): string
    {
        if ($record instanceof Model && filled($record->getKey())) {
            return (string) $record->getKey();
        }

        $attributes = $record instanceof Model
            ? $record->getAttributes()
            : (array) $record;

        return md5(json_encode($attributes));
    }

    protected function configureTable(Table $table, ?string $sortColumn = null, string $sortDirection = 'desc'): Table
    {
        $configured = $table
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10, 25])
            ->striped()
            ->emptyStateHeading('لا توجد بيانات');

        if ($sortColumn !== null) {
            $configured->defaultSort($sortColumn, $sortDirection);
        }

        return $configured;
    }
}
