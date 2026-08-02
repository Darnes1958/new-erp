<?php

namespace App\Filament\Market\Resources\SalesInvoices;

use App\Filament\Market\Resources\SalesInvoices\Pages\EditSell;
use App\Filament\Market\Resources\SalesInvoices\Pages\CreateSalesInvoice;
use App\Filament\Market\Resources\SalesInvoices\Pages\EditSalesInvoice;
use App\Filament\Market\Resources\SalesInvoices\Pages\ListSalesInvoices;
use App\Filament\Market\Resources\SalesInvoices\Pages\SalesReturnEntry;
use App\Filament\Market\Resources\SalesInvoices\Pages\ViewSalesInvoice;
use App\Filament\Market\Resources\SalesInvoices\Schemas\SalesInvoiceForm;
use App\Filament\Market\Resources\SalesInvoices\Schemas\SalesInvoiceInfolist;
use App\Filament\Market\Resources\SalesInvoices\Tables\SalesInvoicesTable;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\SalesInvoice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SalesInvoiceResource extends Resource
{
    protected static ?string $model = SalesInvoice::class;

    protected static ?string $navigationLabel = 'فواتير البيع';

    protected static ?string $modelLabel = 'فاتورة بيع';

    protected static ?string $pluralModelLabel = 'فواتير البيع';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::SalesInvoices;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return SalesInvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesInvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesInvoices::route('/'),
            'create' => CreateSalesInvoice::route('/create'),
            'view' => ViewSalesInvoice::route('/{record}'),
            'edit' => EditSalesInvoice::route('/{record}/edit'),
            'edit-sell' => EditSell::route('/{record}/edit-sell'),
            'sales-return' => SalesReturnEntry::route('/{record}/sales-return'),
        ];
    }
}
