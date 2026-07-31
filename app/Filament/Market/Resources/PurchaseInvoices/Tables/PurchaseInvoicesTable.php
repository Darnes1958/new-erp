<?php

namespace App\Filament\Market\Resources\PurchaseInvoices\Tables;

use App\Filament\Market\Resources\PurchaseInvoices\Pages\EditBuy;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(false)
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('supplier.name')
                    ->label('المورد')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('invoice_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('lines_subtotal')
                    ->label('الإجمالي')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('discount')
                    ->label('خصم')
                    ->numeric(decimalPlaces: 3)
                    ->toggleable(),
                TextColumn::make('amount_paid')
                    ->label('المدفوع')
                    ->numeric(decimalPlaces: 3),
                TextColumn::make('balance')
                    ->label('الباقي')
                    ->numeric(decimalPlaces: 3),
                TextColumn::make('warehouse.name')
                    ->label('المخزن')
                    ->toggleable(),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('المورد')
                    ->relationship('supplier', 'name'),
                SelectFilter::make('warehouse_id')
                    ->label('المخزن')
                    ->relationship('warehouse', 'name'),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
                Action::make('print')
                    ->label('طباعة')
                    ->icon('heroicon-o-printer')
                    ->iconButton()
                    ->url(fn (Model $record): string => route('pdf.purchase-invoice', ['purchaseInvoice' => $record]))
                    ->openUrlInNewTab(),
                Action::make('edit_buy')
                    ->label('تعديل')
                    ->icon('heroicon-m-pencil')
                    ->iconButton()
                    ->color('info')
                    ->url(fn (Model $record): string => EditBuy::getUrl(['record' => $record])),
                DeleteAction::make()->iconButton(),
            ]);
    }
}
