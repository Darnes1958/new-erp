<?php

namespace App\Filament\Market\Pages\Reports\Concerns;

use App\Services\Market\ProfitReportService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

trait ConfiguresProfitReportTable
{
    protected function configureProfitReportTable(Table $table, ProfitReportService $service, int $year): Table
    {
        return $table
            ->records(fn (): \Illuminate\Support\Collection => $service->monthlySummary($year, $this->profitReportWarehouseId()))
            ->heading(fn (): HtmlString => new HtmlString(
                '<div class="text-primary-400 text-lg">الأرباح بالأشهر لسنة '.$year.'</div>',
            ))
            ->columns([
                TextColumn::make('month_name')
                    ->label('الشهر'),
                TextColumn::make('rebh')
                    ->label('هامش الربح')
                    ->numeric(0),
                TextColumn::make('masr')
                    ->label('مصروفات')
                    ->numeric(0),
                TextColumn::make('sal')
                    ->label('مرتبات')
                    ->numeric(0),
                TextColumn::make('rent')
                    ->label('إيجارات')
                    ->numeric(0),
                TextColumn::make('ksm')
                    ->label('خصومات')
                    ->numeric(0),
                TextColumn::make('safi')
                    ->label('صافي الأرباح')
                    ->numeric(0),
            ])
            ->contentFooter(view('filament.market.tables.profit-report-footer'))
            ->defaultSort('month')
            ->paginated([12])
            ->defaultPaginationPageOption(12)
            ->striped();
    }

    protected function profitReportWarehouseId(): ?int
    {
        return null;
    }
}
