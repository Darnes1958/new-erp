<?php

namespace App\Filament\Market\Resources\SalesOfferInvoices\Tables;

use App\Filament\Market\Resources\SalesOfferInvoices\Pages\EditSellOffer;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SalesOfferInvoicesTable
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
                TextColumn::make('customer.name')
                    ->label('الزبون')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('invoice_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('lines_subtotal')
                    ->label('إجمالي البنود')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('extra_cost')
                    ->label('تكاليف إضافية')
                    ->numeric(3)
                    ->toggleable(),
                TextColumn::make('grand_total')
                    ->label('الإجمالي')
                    ->numeric(3)
                    ->sortable(),
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
            ->emptyStateHeading('لا توجد فواتير عرض')
            ->filters([
                SelectFilter::make('customer_id')
                    ->label('الزبون')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('warehouse_id')
                    ->label('المخزن')
                    ->relationship('warehouse', 'name'),
                TernaryFilter::make('is_retail')
                    ->label('بيع قطاعي')
                    ->placeholder('الكل')
                    ->trueLabel('قطاعي')
                    ->falseLabel('جملة'),
                Filter::make('invoice_date')
                    ->label('التاريخ')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('من تاريخ'),
                        DatePicker::make('date_to')
                            ->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('invoice_date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('invoice_date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
                Action::make('print')
                    ->label('طباعة')
                    ->icon('heroicon-o-printer')
                    ->iconButton()
                    ->url(fn (Model $record): string => route('pdf.sales-offer-invoice', ['salesOfferInvoice' => $record]))
                    ->openUrlInNewTab(),
                Action::make('edit_sell_offer')
                    ->label('تعديل')
                    ->icon('heroicon-m-pencil')
                    ->iconButton()
                    ->color('info')
                    ->url(fn (Model $record): string => EditSellOffer::getUrl(['record' => $record])),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (): bool => Auth::user()?->can('الغاء مبيعات') || Auth::user()?->is_prog),
            ]);
    }
}
