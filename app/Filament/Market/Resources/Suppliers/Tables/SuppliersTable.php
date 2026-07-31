<?php

namespace App\Filament\Market\Resources\Suppliers\Tables;

use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('address')
                    ->label('العنوان')
                    ->toggleable(),
                TextColumn::make('mdar')
                    ->label('مدار')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('libyana')
                    ->label('لبيانا')
                    ->searchable()
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->striped()
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->modalHeading('حذف مورد')
                    ->modalDescription('هل أنت متأكد من حذف هذا المورد؟')
                    ->hidden(fn (Supplier $record): bool => PurchaseInvoice::query()
                        ->where('supplier_id', $record->id)
                        ->exists()
                        || SupplierPayment::query()
                            ->where('supplier_id', $record->id)
                            ->exists()),
            ]);
    }
}
