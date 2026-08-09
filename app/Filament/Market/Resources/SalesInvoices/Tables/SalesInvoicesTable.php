<?php

namespace App\Filament\Market\Resources\SalesInvoices\Tables;

use App\Filament\Market\Resources\SalesInvoices\Pages\EditSell;
use App\Filament\Market\Resources\SalesInvoices\Pages\SalesReturnEntry;
use App\Models\SalesInvoice;
use App\Services\Inventory\SalesInvoiceCancelService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
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
                TextColumn::make('grand_total')
                    ->label('الإجمالي')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->label('المدفوع')
                    ->numeric(3),
                TextColumn::make('balance')
                    ->label('الباقي')
                    ->numeric(3),
                TextColumn::make('warehouse.name')
                    ->label('المخزن')
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
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
                    ->url(fn ($record): string => route('pdf.sales-invoice', ['salesInvoice' => $record]))
                    ->openUrlInNewTab(),
                Action::make('edit_sell')
                    ->label('تعديل')
                    ->icon('heroicon-m-pencil')
                    ->iconButton()
                    ->color('info')
                    ->url(fn ($record): string => EditSell::getUrl(['record' => $record])),
                Action::make('sales_return')
                    ->label('ترجيع')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->iconButton()
                    ->color('primary')
                    ->tooltip('ترجيع')
                    ->url(fn ($record): string => SalesReturnEntry::getUrl(['record' => $record])),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (SalesInvoice $record): bool => $record->canBeDeleted())
                    ->before(function (SalesInvoice $record): void {
                        try {
                            $record->assertCanBeDeleted();
                        } catch (\RuntimeException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->warning()
                                ->send();

                            throw $exception;
                        }
                    })
                    ->using(fn (SalesInvoice $record) => app(SalesInvoiceCancelService::class)->cancel($record)),
            ]);
    }
}
