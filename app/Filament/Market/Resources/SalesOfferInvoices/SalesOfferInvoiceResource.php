<?php

namespace App\Filament\Market\Resources\SalesOfferInvoices;

use App\Filament\Market\Resources\SalesOfferInvoices\Pages\EditSellOffer;
use App\Filament\Market\Resources\SalesOfferInvoices\Pages\ListSalesOfferInvoices;
use App\Filament\Market\Resources\SalesOfferInvoices\Pages\ViewSalesOfferInvoice;
use App\Filament\Market\Resources\SalesOfferInvoices\Schemas\SalesOfferInvoiceInfolist;
use App\Filament\Market\Resources\SalesOfferInvoices\Tables\SalesOfferInvoicesTable;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Filament\Support\AuthorizesErpResource;
use App\Models\SalesOfferInvoice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SalesOfferInvoiceResource extends Resource
{
    use AuthorizesErpResource;

    protected static ?string $model = SalesOfferInvoice::class;

    protected static ?string $navigationLabel = 'فواتير العرض';

    protected static ?string $modelLabel = 'فاتورة عرض';

    protected static ?string $pluralModelLabel = 'فواتير العرض';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::SalesInvoices;

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    protected static function createPermissions(): array
    {
        return ['ادخال مبيعات'];
    }

    protected static function editPermissions(): array
    {
        return ['تعديل مبيعات'];
    }

    protected static function deletePermissions(): array
    {
        return ['الغاء مبيعات'];
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesOfferInvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesOfferInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesOfferInvoices::route('/'),
            'view' => ViewSalesOfferInvoice::route('/{record}'),
            'edit-sell-offer' => EditSellOffer::route('/{record}/edit-sell-offer'),
        ];
    }
}
