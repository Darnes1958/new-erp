<?php

namespace App\Filament\Market\Pages\InpBuy\Schemas;

use App\Models\Supplier;
use App\Models\Warehouse;
use App\Support\CompanySettings;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class PurchaseHeaderForm
{
    public static function configure(Schema $schema, object $page): Schema
    {
        return $schema
            ->model(\App\Models\PurchaseInvoiceWork::class)
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
                        Select::make('supplier_id')
                            ->label('المورد')
                            ->searchable()
                            ->preload()
                            ->afterStateUpdated(function ($state) use ($page): void {
                                $page->work->supplier_id = $state;
                                $page->work->save();
                            })
                            ->relationship('supplier', 'name')
                            ->live()
                            ->required()
                            ->inlineLabel()
                            ->columnSpan(3)
                            ->createOptionForm([
                                Section::make('ادخال مورد جديد')->schema([
                                    TextInput::make('name')
                                        ->required()
                                        ->unique(Supplier::class)
                                        ->label('الاسم'),
                                    TextInput::make('address')->label('العنوان'),
                                    TextInput::make('mdar')->label('مدار'),
                                    TextInput::make('libyana')->label('لبيانا'),
                                    Hidden::make('created_by')->default(Auth::id()),
                                ]),
                            ])
                            ->editOptionForm([
                                Section::make('تعديل بيانات مورد')->schema([
                                    TextInput::make('name')->required()->label('الاسم'),
                                    TextInput::make('address')->label('العنوان'),
                                    TextInput::make('mdar')->label('مدار'),
                                    TextInput::make('libyana')->label('لبيانا'),
                                    Hidden::make('created_by')->default(Auth::id()),
                                ])->columns(2),
                            ])
                            ->editOptionAction(fn ($action) => $action->visible(fn () => $page->work->supplier_id != 1))
                            ->id('supplier_id'),
                        Select::make('warehouse_id')
                            ->label('مكان التخزين')
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
                            ->createOptionForm([
                                Section::make('ادخال مكان تخزين')->schema([
                                    TextInput::make('name')
                                        ->required()
                                        ->unique(Warehouse::class)
                                        ->label('الاسم'),
                                    TextInput::make('warehouse_type')
                                        ->label('النوع')
                                        ->numeric()
                                        ->default(1),
                                ]),
                            ])
                            ->id('warehouse_id')
                            ->visible(CompanySettings::multiWarehouse()),
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
                                $page->refreshLineBuyPrice();
                            })
                            ->id('payment_method_id'),
                        TextInput::make('lines_subtotal')
                            ->label('إجمالي الفاتورة')
                            ->columnSpan(2)
                            ->inlineLabel()
                            ->readOnly(),
                        TextInput::make('discount')
                            ->label('خصم')
                            ->columnSpan(2)
                            ->inlineLabel()
                            ->numeric()
                            ->default(0)
                            ->live(onBlur: true)
                            ->extraInputAttributes(['wire:keydown.enter' => 'updatePay'])
                            ->afterStateUpdated(fn () => $page->refreshPaymentTotals())
                            ->id('discount'),
                        TextInput::make('amount_paid')
                            ->label('المدفوع')
                            ->columnSpan(2)
                            ->extraInputAttributes(['wire:keydown.enter' => 'updatePay'])
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn () => $page->refreshPaymentTotals())
                            ->inlineLabel()
                            ->helperText(fn () => $page->isEditMode() ? 'المدفوع أثناء إدخال الفاتورة فقط' : null)
                            ->default('0')
                            ->id('amount_paid'),
                        TextInput::make('balance')
                            ->label('المتبقي')
                            ->columnSpan(2)
                            ->inlineLabel()
                            ->readOnly()
                            ->default('0'),
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
