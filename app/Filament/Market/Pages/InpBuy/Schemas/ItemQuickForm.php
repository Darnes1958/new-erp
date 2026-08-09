<?php

namespace App\Filament\Market\Pages\InpBuy\Schemas;

use App\Models\Item;
use App\Models\ItemPrice;
use App\Support\CompanySettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

class ItemQuickForm
{
    public static function nextBarcode(): string
    {
        $maxId = Item::query()->max('id') ?? 0;

        return (string) ($maxId + 1);
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function schema(bool $forEdit = false): array
    {
        $barcodeEnabled = CompanySettings::barcodeEnabled();

        return [
            Section::make('')
                ->schema([
                    Hidden::make('id')
                        ->visible($forEdit),
                    TextInput::make('name')
                        ->label('اسم الصنف')
                        ->autocomplete(false)
                        ->required()
                        ->unique(ignoreRecord: $forEdit, table: Item::class)
                        ->validationMessages([
                            'unique' => ':attribute مخزون مسبقاً',
                        ]),
                    TextInput::make('barcode')
                        ->label('الباركود')
                        ->required()
                        ->readOnly(! $barcodeEnabled)
                        ->default(fn (): ?string => $barcodeEnabled ? null : static::nextBarcode())
                        ->suffixAction(
                            Action::make('gen_barcode')
                                ->icon(Heroicon::Plus)
                                ->color('success')
                                ->iconButton()
                                ->hiddenLabel()
                                ->visible($barcodeEnabled)
                                ->action(fn (Set $set) => $set('barcode', static::nextBarcode()))
                        )
                        ->unique(ignoreRecord: $forEdit, table: Item::class)
                        ->validationMessages([
                            'unique' => 'هذا الـ :attribute مخزون مسبقاً',
                        ]),
                    Select::make('primary_unit_id')
                        ->label('الوحدة')
                        ->relationship('primaryUnit', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('الاسم')
                                ->required()
                                ->unique(),
                        ]),
                    Select::make('item_type_id')
                        ->label('التصنيف')
                        ->relationship('itemType', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('الاسم')
                                ->required()
                                ->unique(),
                        ]),
                    TextInput::make('default_buy_price')
                        ->label('سعر الشراء')
                        ->numeric()
                        ->required()
                        ->gt(0)
                        ->validationMessages([
                            'gt' => 'سعر الشراء لا يجوز أن يكون صفر',
                        ]),
                    TextInput::make('sell_price_cash')
                        ->label('سعر البيع نقداً')
                        ->numeric(),
                    TextInput::make('sell_price_installment')
                        ->label('سعر البيع تقسيطاً')
                        ->numeric(),
                    Select::make('brand_id')
                        ->label('الشركة المصنعة')
                        ->relationship('brand', 'name')
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('الاسم')
                                ->required()
                                ->unique(),
                        ]),
                    Hidden::make('is_active')
                        ->default(true),
                ])
                ->columns(2),
        ];
    }

    public static function createFromData(array $data): Item
    {
        if (! filled($data['barcode'] ?? null)) {
            $data['barcode'] = static::nextBarcode();
        }

        $item = Item::query()->create([
            'name' => $data['name'],
            'barcode' => $data['barcode'],
            'primary_unit_id' => $data['primary_unit_id'],
            'item_type_id' => $data['item_type_id'],
            'brand_id' => $data['brand_id'] ?? null,
            'default_buy_price' => $data['default_buy_price'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        static::syncSellPrices($item, $data);

        return $item;
    }

    public static function updateFromData(Item $item, array $data): Item
    {
        $item->update([
            'name' => $data['name'],
            'barcode' => $data['barcode'],
            'primary_unit_id' => $data['primary_unit_id'],
            'item_type_id' => $data['item_type_id'],
            'brand_id' => $data['brand_id'] ?? null,
            'default_buy_price' => $data['default_buy_price'],
        ]);

        static::syncSellPrices($item, $data);

        return $item->refresh();
    }

    public static function itemToFormData(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'barcode' => $item->barcode,
            'primary_unit_id' => $item->primary_unit_id,
            'item_type_id' => $item->item_type_id,
            'brand_id' => $item->brand_id,
            'default_buy_price' => $item->default_buy_price,
            'sell_price_cash' => $item->sellPriceFor(1) ?: null,
            'sell_price_installment' => $item->sellPriceFor(3) ?: null,
            'is_active' => $item->is_active,
        ];
    }

    protected static function syncSellPrices(Item $item, array $data): void
    {
        if (filled($data['sell_price_cash'] ?? null) && (float) $data['sell_price_cash'] > 0) {
            ItemPrice::query()->updateOrCreate(
                [
                    'item_id' => $item->id,
                    'payment_method_id' => 1,
                    'price_kind' => 'sell',
                ],
                [
                    'price_primary' => (float) $data['sell_price_cash'],
                    'price_secondary' => 0,
                ],
            );
        }

        if (filled($data['sell_price_installment'] ?? null) && (float) $data['sell_price_installment'] > 0) {
            ItemPrice::query()->updateOrCreate(
                [
                    'item_id' => $item->id,
                    'payment_method_id' => 3,
                    'price_kind' => 'sell',
                ],
                [
                    'price_primary' => (float) $data['sell_price_installment'],
                    'price_secondary' => 0,
                ],
            );
        }
    }
}
