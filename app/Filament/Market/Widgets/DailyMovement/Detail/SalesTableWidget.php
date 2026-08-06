<?php

namespace App\Filament\Market\Widgets\DailyMovement\Detail;

use App\Filament\Market\Widgets\DailyMovement\BaseDailyMovementTableWidget;
use App\Models\SalesInvoice;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalesTableWidget extends BaseDailyMovementTableWidget
{
    public function table(Table $table): Table
    {
        $service = $this->dailyMovementService();

        return $this->configureTable($table, 'invoice_date')
            ->query(fn () => $service->salesDetailQuery($this->dateFrom, $this->dateTo, $this->warehouseId))
            ->heading($this->sectionHeading('فواتير المبيعات', 'text-danger-600'))
            ->columns([
                TextColumn::make('id')
                    ->label('رقم الفاتورة'),
                TextColumn::make('invoice_date')
                    ->label('التاريخ')
                    ->date('Y-m-d'),
                TextColumn::make('customer.name')
                    ->label('الزبون'),
                TextColumn::make('grand_total')
                    ->label('الإجمالي')
                    ->numeric(3),
                TextColumn::make('amount_paid')
                    ->label('المدفوع')
                    ->numeric(3),
                TextColumn::make('balance')
                    ->label('المتبقي')
                    ->numeric(3),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(40),
            ])
            ->recordActions([
                Action::make('print')
                    ->icon('heroicon-o-printer')
                    ->iconButton()
                    ->url(fn (SalesInvoice $record): string => route('pdf.sales-invoice', ['salesInvoice' => $record->id]))
                    ->openUrlInNewTab(),
            ]);
    }
}
