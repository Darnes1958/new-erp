<?php

namespace App\Filament\Market\Resources\Customers\Tables;

use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\SalesInvoice;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customerType.name')
                    ->label('التصنيف')
                    ->sortable(),
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
            ->filters([
                SelectFilter::make('customer_type_id')
                    ->label('التصنيف')
                    ->relationship('customerType', 'name'),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->modalHeading('حذف زبون')
                    ->modalDescription('هل أنت متأكد من حذف هذا الزبون؟')
                    ->hidden(fn (Customer $record): bool => SalesInvoice::query()
                        ->where('customer_id', $record->id)
                        ->exists()
                        || CustomerReceipt::query()
                            ->where('customer_id', $record->id)
                            ->exists()),
            ]);
    }
}
