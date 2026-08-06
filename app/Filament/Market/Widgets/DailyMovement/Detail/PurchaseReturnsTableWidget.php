<?php

namespace App\Filament\Market\Widgets\DailyMovement\Detail;

use App\Filament\Market\Widgets\DailyMovement\BaseDailyMovementTableWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PurchaseReturnsTableWidget extends BaseDailyMovementTableWidget
{
    public function table(Table $table): Table
    {
        $service = $this->dailyMovementService();

        return $this->configureTable($table, 'return_date')
            ->query(fn () => $service->purchaseReturnsDetailQuery($this->dateFrom, $this->dateTo, $this->warehouseId))
            ->heading($this->sectionHeading('ترجيع مشتريات'))
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم'),
                TextColumn::make('return_date')
                    ->label('التاريخ')
                    ->date('Y-m-d'),
                TextColumn::make('purchaseInvoice.supplier.name')
                    ->label('المورد'),
                TextColumn::make('item.name')
                    ->label('الصنف'),
                TextColumn::make('qty_primary')
                    ->label('الكمية')
                    ->numeric(3),
                TextColumn::make('line_total')
                    ->label('الإجمالي')
                    ->numeric(3),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(40),
            ]);
    }
}
