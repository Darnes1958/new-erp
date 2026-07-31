<?php

namespace App\Filament\Market\Resources\Customers;

use App\Filament\Market\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Market\Resources\Customers\Pages\EditCustomer;
use App\Filament\Market\Resources\Customers\Pages\ListCustomers;
use App\Filament\Market\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Market\Resources\Customers\Tables\CustomersTable;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\Customer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationLabel = 'زبائن';

    protected static ?string $modelLabel = 'زبون';

    protected static ?string $pluralModelLabel = 'زبائن';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::CustomersSuppliers;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
