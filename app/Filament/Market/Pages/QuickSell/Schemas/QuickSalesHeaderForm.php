<?php

namespace App\Filament\Market\Pages\QuickSell\Schemas;

use App\Models\SalesInvoiceLineWork;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class QuickSalesHeaderForm
{
    public static function configure(Schema $schema, object $page): Schema
    {
        return $schema
            ->model(\App\Models\SalesInvoiceWork::class)
            ->statePath('headerData')
            ->columns(8)
            ->components([
                Hidden::make('customer_id')
                    ->default(1),
                DatePicker::make('invoice_date')
                    ->id('invoice_date')
                    ->label('التاريخ')
                    ->columnSpan(2)
                    ->inlineLabel()
                    ->afterStateUpdated(function ($state) use ($page): void {
                        $page->work->invoice_date = $state;
                        $page->work->save();
                    })
                    ->required(),
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
                        $page->refreshPaymentTotals();
                    })
                    ->id('payment_method_id'),
                Select::make('warehouse_id')
                    ->label('نقطة البيع')
                    ->relationship('warehouse', 'name')
                    ->live()
                    ->required()
                    ->disabled(fn (): bool => $page->hasInvoiceLines())
                    ->inlineLabel()
                    ->columnSpan(2)
                    ->afterStateUpdated(function ($state) use ($page): void {
                        $page->work->warehouse_id = $state;
                        $page->work->save();
                    })
                    ->id('warehouse_id'),
                TextInput::make('grand_total')
                    ->label('الإجمالي')
                    ->columnSpan(2)
                    ->inlineLabel()
                    ->readOnly(),
            ]);
    }
}
