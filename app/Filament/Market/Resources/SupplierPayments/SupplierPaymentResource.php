<?php

namespace App\Filament\Market\Resources\SupplierPayments;

use App\Filament\Market\Resources\SupplierPayments\Pages\CreateSupplierPayment;
use App\Filament\Market\Resources\SupplierPayments\Pages\EditSupplierPayment;
use App\Filament\Market\Resources\SupplierPayments\Pages\ListSupplierPayments;
use App\Filament\Market\Resources\SupplierPayments\Schemas\SupplierPaymentForm;
use App\Filament\Market\Resources\SupplierPayments\Tables\SupplierPaymentsTable;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Filament\Support\AuthorizesErpResource;
use App\Models\SupplierPayment;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SupplierPaymentResource extends Resource
{
    use AuthorizesErpResource;

    protected static ?string $model = SupplierPayment::class;

    protected static ?string $navigationLabel = 'إيصالات موردين';

    protected static ?string $modelLabel = 'إيصال مورد';

    protected static ?string $pluralModelLabel = 'إيصالات موردين';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::ReceiptsAndPayments;

    protected static ?int $navigationSort = 2;

    protected static function createPermissions(): array
    {
        return ['ادخال ايصالات موردين', 'ايصالات موردين'];
    }

    protected static function deletePermissions(): array
    {
        return ['الغاء ايصالات موردين'];
    }

    public static function form(Schema $schema): Schema
    {
        return SupplierPaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupplierPaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierPayments::route('/'),
            'create' => CreateSupplierPayment::route('/create'),
            'edit' => EditSupplierPayment::route('/{record}/edit'),
        ];
    }
}
