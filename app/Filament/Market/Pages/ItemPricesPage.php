<?php

namespace App\Filament\Market\Pages;

use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\Item;
use App\Services\Inventory\PurchaseInventoryService;
use App\Support\Authz;
use App\Support\ErpNumber;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ItemPricesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'أسعار الأصناف';

    protected static ?string $title = 'أسعار الأصناف';

    protected static ?string $slug = 'item-prices';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::WarehousesItems;

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.market.pages.list-table';

    protected ?string $heading = 'أسعار الأصناف';

    public static function canAccess(): bool
    {
        return Authz::can(
            'استفسار عن الاسعار',
            'المخزون',
            'تعديل مخازن',
            'ادخال مخازن',
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function table(Table $table): Table
    {
        $canEdit = $this->canEditPrices();

        return $table
            ->defaultNumberLocale(ErpNumber::locale())
            ->query(
                Item::query()
                    ->with([
                        'itemType',
                        'prices' => fn ($query) => $query->where('price_kind', 'sell'),
                    ])
            )
            ->defaultSort('id')
            ->striped()
            ->searchPlaceholder('بحث برقم أو اسم الصنف')
            ->columns([
                TextColumn::make('id')
                    ->label('رقم الصنف')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('اسم الصنف')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('itemType.name')
                    ->label('التصنيف')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—'),
                $this->priceColumn('sell_cash', 'سعر البيع نقداً', 1, $canEdit),
                $this->priceColumn('sell_check', 'سعر البيع صك', 2, $canEdit),
                $this->priceColumn('sell_installment', 'سعر البيع تقسيط', 3, $canEdit),
            ])
            ->filters([
                SelectFilter::make('item_type_id')
                    ->label('تصنيف الصنف')
                    ->relationship('itemType', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('لا توجد أصناف');
    }

    protected function priceColumn(string $name, string $label, int $paymentMethodId, bool $canEdit): TextInputColumn
    {
        return TextInputColumn::make($name)
            ->label($label)
            ->type('number')
            ->step('0.001')
            ->inputMode('decimal')
            ->alignEnd()
            ->disabled(! $canEdit)
            ->getStateUsing(fn (Item $record): string => number_format($record->sellPriceFor($paymentMethodId), 3, '.', ''))
            ->updateStateUsing(function (Item $record, mixed $state) use ($paymentMethodId): void {
                $this->saveSellPrice($record, $paymentMethodId, $state);
            });
    }

    protected function canEditPrices(): bool
    {
        return Authz::can('تعديل مخازن', 'المخزون', 'ادخال مخازن');
    }

    protected function saveSellPrice(Item $record, int $paymentMethodId, mixed $state): void
    {
        if (! $this->canEditPrices()) {
            return;
        }

        $price = is_numeric($state) ? round((float) $state, 3) : 0.0;
        $price = max(0.0, $price);

        app(PurchaseInventoryService::class)->updateSellPrice((int) $record->id, $paymentMethodId, $price);

        $record->unsetRelation('prices');
        $record->load(['prices' => fn ($query) => $query->where('price_kind', 'sell')]);
    }
}
