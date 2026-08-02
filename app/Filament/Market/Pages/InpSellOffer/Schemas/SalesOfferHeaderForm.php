<?php

namespace App\Filament\Market\Pages\InpSellOffer\Schemas;

use App\Models\Customer;
use App\Support\CompanySettings;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class SalesOfferHeaderForm
{
    public static function configure(Schema $schema, object $page): Schema
    {
        return $schema
            ->model(\App\Models\SalesOfferInvoiceWork::class)
            ->statePath('headerData')
            ->columns(8)
            ->components([
                DatePicker::make('invoice_date')
                    ->id('invoice_date')
                    ->autofocus()
                    ->label('التاريخ')
                    ->columnSpan(2)
                    ->inlineLabel()
                    ->afterStateUpdated(function ($state) use ($page): void {
                        $page->work->invoice_date = $state;
                        $page->work->save();
                    })
                    ->required(),
                Select::make('customer_id')
                    ->label('الزبون')
                    ->searchable()
                    ->preload()
                    ->relationship('customer', 'name')
                    ->live()
                    ->required()
                    ->inlineLabel()
                    ->columnSpan(3)
                    ->afterStateUpdated(function ($state) use ($page): void {
                        $page->work->customer_id = $state;
                        $page->work->save();
                    })
                    ->createOptionForm([
                        Section::make('ادخال زبون جديد')->schema([
                            TextInput::make('name')
                                ->required()
                                ->unique(Customer::class)
                                ->label('الاسم'),
                            TextInput::make('address')->label('العنوان'),
                            TextInput::make('phone')->label('الهاتف'),
                            Hidden::make('created_by')->default(Auth::id()),
                        ]),
                    ])
                    ->id('customer_id'),
                Select::make('warehouse_id')
                    ->label('نقطة البيع')
                    ->relationship('warehouse', 'name')
                    ->live()
                    ->required(fn (): bool => CompanySettings::multiWarehouse())
                    ->disabled(fn () => $page->isEditMode())
                    ->inlineLabel()
                    ->columnSpan(3)
                    ->afterStateUpdated(function ($state) use ($page): void {
                        $page->work->warehouse_id = $state;
                        $page->work->save();
                    })
                    ->visible(CompanySettings::multiWarehouse())
                    ->id('warehouse_id'),
                Select::make('payment_method_id')
                    ->label('طريقة الدفع')
                    ->columnSpan(2)
                    ->inlineLabel()
                    ->live()
                    ->default(1)
                    ->relationship('paymentMethod', 'name')
                    ->required()
                    ->afterStateUpdated(function ($state) use ($page): void {
                        $page->work->payment_method_id = $state;
                        $page->work->save();
                        $page->refreshLineSellPrice();
                        $page->refreshOfferTotals();
                    })
                    ->id('payment_method_id'),
                Toggle::make('is_retail')
                    ->label('بيع قطاعي')
                    ->columnSpan(2)
                    ->inlineLabel()
                    ->default(true)
                    ->live()
                    ->afterStateUpdated(function ($state) use ($page): void {
                        $page->work->is_retail = (bool) $state;
                        $page->work->save();
                        $page->refreshLineSellPrice();
                    }),
                TextInput::make('lines_subtotal')
                    ->label('اجمالي البنود')
                    ->columnSpan(2)
                    ->inlineLabel()
                    ->readOnly(),
                TextInput::make('extra_cost')
                    ->label('تكاليف إضافية')
                    ->columnSpan(2)
                    ->inlineLabel()
                    ->numeric()
                    ->default(0)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn () => $page->refreshOfferTotals()),
                TextInput::make('rate_markup')
                    ->label('النسبة %')
                    ->columnSpan(2)
                    ->inlineLabel()
                    ->numeric()
                    ->default(0)
                    ->visible(fn () => $page->usesInstallmentMarkup())
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn () => $page->refreshOfferTotals()),
                TextInput::make('difference_amount')
                    ->label('فرق عملة')
                    ->columnSpan(2)
                    ->inlineLabel()
                    ->readOnly()
                    ->visible(fn () => $page->usesInstallmentMarkup()),
                TextInput::make('grand_total')
                    ->label('الإجمالي')
                    ->columnSpan(2)
                    ->inlineLabel()
                    ->readOnly(),
                TextInput::make('notes')
                    ->hiddenLabel()
                    ->prefix('ملاحظات')
                    ->columnSpanFull()
                    ->afterStateUpdated(function ($state) use ($page): void {
                        $page->work->notes = $state;
                        $page->work->save();
                    }),
            ]);
    }
}
