<?php

namespace App\Filament\Market\Resources\PurchaseInvoices;

use App\Filament\Market\Resources\PurchaseInvoices\Pages\CreatePurchaseInvoice;
use App\Filament\Market\Resources\PurchaseInvoices\Pages\EditBuy;
use App\Filament\Market\Resources\PurchaseInvoices\Pages\EditPurchaseInvoice;
use App\Filament\Market\Resources\PurchaseInvoices\Pages\ListPurchaseInvoices;
use App\Filament\Market\Resources\PurchaseInvoices\Pages\PurchaseReturnEntry;
use App\Filament\Market\Resources\PurchaseInvoices\Pages\ViewPurchaseInvoice;
use App\Filament\Market\Resources\PurchaseInvoices\Schemas\PurchaseInvoiceForm;
use App\Filament\Market\Resources\PurchaseInvoices\Schemas\PurchaseInvoiceInfolist;
use App\Filament\Market\Resources\PurchaseInvoices\Tables\PurchaseInvoicesTable;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Filament\Support\AuthorizesErpResource;
use App\Models\PurchaseInvoice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PurchaseInvoiceResource extends Resource
{
    use AuthorizesErpResource;

    protected static ?string $model = PurchaseInvoice::class;

    protected static ?string $navigationLabel = 'فواتير الشراء';

    protected static ?string $modelLabel = 'فاتورة شراء';

    protected static ?string $pluralModelLabel = 'فواتير الشراء';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::PurchaseInvoices;

    protected static ?int $navigationSort = 2;

    protected static function createPermissions(): array
    {
        return ['ادخال مشتريات'];
    }

    protected static function editPermissions(): array
    {
        return ['تعديل مشتريات', 'ادخال مشتريات'];
    }

    protected static function deletePermissions(): array
    {
        return ['الغاء مشتريات'];
    }

    protected static function accessPermissions(): array
    {
        return [
            'ادخال مشتريات',
            'تعديل مشتريات',
            'الغاء مشتريات',
            'ادخال ترجيع مشتريات',
            'الغاء ترجيع مشتريات',
            'ترجيع مشتريات',
            'تقارير مشتريات',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseInvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseInvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseInvoices::route('/'),
            'create' => CreatePurchaseInvoice::route('/create'),
            'view' => ViewPurchaseInvoice::route('/{record}'),
            'edit' => EditPurchaseInvoice::route('/{record}/edit'),
            'edit-buy' => EditBuy::route('/{record}/edit-buy'),
            'purchase-return' => PurchaseReturnEntry::route('/{record}/purchase-return'),
        ];
    }
}
