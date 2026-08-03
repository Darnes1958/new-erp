<?php

namespace App\Support\Filament;

use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;

/**
 * Filament shows a summary header row ("الملخص" + column labels) unless page
 * summaries are disabled, and a row heading unless translation strings are empty.
 */
class TableSummaries
{
    public static function sum(): Sum
    {
        return Sum::make()->label('');
    }

    public static function applyDefaults(Table $table): Table
    {
        return $table->summaries(pageCondition: false);
    }
}
