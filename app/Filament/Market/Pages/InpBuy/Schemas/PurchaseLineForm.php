<?php

namespace App\Filament\Market\Pages\InpBuy\Schemas;

use App\Filament\Market\Tables\ItemPickerTable;
use App\Models\Item;
use App\Support\CompanySettings;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TableSelect;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class PurchaseLineForm
{
    public static function configure(Schema $schema, object $page): Schema
    {
        return $schema
            ->model(\App\Models\PurchaseInvoiceLineWork::class)
            ->statePath('lineData')
            ->columns(1)
            ->components([
                TextInput::make('barcode')
                    ->hiddenLabel()
                    ->prefix('الباركود')
                    ->columnSpanFull()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state) => $page->checkBarcode($state))
                    ->extraAttributes(['wire:keydown.enter.prevent' => 'submitBarcode'])
                    ->autocomplete(false)
                    ->id('barcode'),
                Select::make('item_id')
                    ->hiddenLabel()
                    ->prefix('الصنف')
                    ->columnSpanFull()
                    ->searchable()
                    ->preload()
                    ->relationship('item', 'name')
                    ->live()
                    ->required()
                    ->afterStateUpdated(fn ($state) => $page->checkItem($state))
                    ->suffixAction(
                        Action::make('select_item')
                            ->hiddenLabel()
                            ->icon(Heroicon::MagnifyingGlass)
                            ->schema([
                                TableSelect::make('item_id')
                                    ->hiddenLabel()
                                    ->relationship('item', 'name')
                                    ->tableConfiguration(ItemPickerTable::class)
                                    ->columnSpanFull(),
                            ])
                            ->fillForm(fn (): array => [
                                'item_id' => $page->lineData['item_id'] ?? null,
                            ])
                            ->action(function (array $data) use ($page): void {
                                $page->lineForm->fill(array_merge($page->lineData, [
                                    'item_id' => $data['item_id'],
                                ]));
                                $page->checkItem((int) $data['item_id']);
                            })
                    )
                    ->afterContent(
                        CreateAction::make('create_item')
                            ->model(Item::class)
                            ->modalHeading(new HtmlString('<span class="text-primary-600">إضافة صنف جديد</span>'))
                            ->icon(Heroicon::Plus)
                            ->color('success')
                            ->iconButton()
                            ->schema(ItemQuickForm::schema())
                            ->using(fn (array $data): Item => ItemQuickForm::createFromData($data))
                            ->after(function (Set $set, Item $record) use ($page): void {
                                $set('item_id', $record->id);
                                $page->checkItem($record->id);
                            })
                    )
                    ->id('item_id'),
                DatePicker::make('expiry_date')
                    ->label('تاريخ الصلاحية')
                    ->columnSpanFull()
                    ->extraAttributes(['wire:keydown.enter.prevent' => 'focusUnitCost'])
                    ->visible(CompanySettings::hasExpiryDates()),
                TextInput::make('unit_cost_primary')
                    ->hiddenLabel()
                    ->prefix('السعر')
                    ->prefixIcon('heroicon-m-currency-dollar')
                    ->prefixIconColor('info')
                    ->columnSpanFull()
                    ->numeric()
                    ->live()
                    ->required()
                    ->gt(0)
                    ->validationMessages([
                        'gt' => 'سعر الشراء لا يجوز أن يكون صفر',
                    ])
                    ->extraAttributes(['wire:keydown.enter.prevent' => 'focusQuantity'])
                    ->id('unit_cost_primary'),
                TextInput::make('qty_primary')
                    ->hiddenLabel()
                    ->prefix('الكمية')
                    ->prefixIcon('heroicon-m-shopping-cart')
                    ->prefixIconColor('warning')
                    ->columnSpanFull()
                    ->numeric()
                    ->required()
                    ->extraAttributes(['wire:keydown.enter.prevent' => 'addLine'])
                    ->id('qty_primary'),
                TextInput::make('price_nakdy')
                    ->hiddenLabel()
                    ->prefix('سعر البيع نقداً')
                    ->prefixIcon('heroicon-m-currency-dollar')
                    ->prefixIconColor('success')
                    ->columnSpanFull()
                    ->numeric()
                    ->extraAttributes(['wire:keydown.enter.prevent' => 'saveCashSellPrice'])
                    ->id('price_nakdy'),
                TextInput::make('price_takseet')
                    ->hiddenLabel()
                    ->prefix('سعر البيع تقسيطاً')
                    ->prefixIcon('heroicon-m-currency-dollar')
                    ->prefixIconColor('success')
                    ->columnSpanFull()
                    ->numeric()
                    ->extraAttributes(['wire:keydown.enter.prevent' => 'saveInstallmentSellPrice'])
                    ->id('price_takseet'),
                Hidden::make('purchase_invoice_work_id')->default(fn () => Auth::id()),
                Hidden::make('created_by')->default(fn () => Auth::id()),
            ]);
    }
}
