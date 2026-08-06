<?php

namespace App\Filament\Market\Widgets\DailyMovement\Detail;

use App\Filament\Market\Widgets\DailyMovement\BaseDailyMovementTableWidget;
use App\Models\PurchaseInvoice;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PurchasesTableWidget extends BaseDailyMovementTableWidget
{
    public function table(Table $table): Table
    {
        $service = $this->dailyMovementService();

        return $this->configureTable($table, 'invoice_date')
            ->query(fn () => $service->purchasesDetailQuery($this->dateFrom, $this->dateTo, $this->warehouseId))
            ->heading($this->sectionHeading('فواتير المشتريات'))
            ->columns([
                TextColumn::make('id')
                    ->label('رقم الفاتورة'),
                TextColumn::make('invoice_date')
                    ->label('التاريخ')
                    ->date('Y-m-d'),
                TextColumn::make('supplier.name')
                    ->label('المورد'),
                TextColumn::make('invoice_total')
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
                    ->url(fn (PurchaseInvoice $record): string => route('pdf.purchase-invoice', ['purchaseInvoice' => $record->id]))
                    ->openUrlInNewTab(),
            ]);
    }
}
