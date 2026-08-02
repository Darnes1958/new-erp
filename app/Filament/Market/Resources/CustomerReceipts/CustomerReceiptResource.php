<?php

namespace App\Filament\Market\Resources\CustomerReceipts;

use App\Filament\Market\Resources\CustomerReceipts\Pages\CreateCustomerReceipt;
use App\Filament\Market\Resources\CustomerReceipts\Pages\EditCustomerReceipt;
use App\Filament\Market\Resources\CustomerReceipts\Pages\ListCustomerReceipts;
use App\Filament\Market\Resources\CustomerReceipts\Schemas\CustomerReceiptForm;
use App\Filament\Market\Resources\CustomerReceipts\Tables\CustomerReceiptsTable;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\CustomerReceipt;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CustomerReceiptResource extends Resource
{
    protected static ?string $model = CustomerReceipt::class;

    protected static ?string $navigationLabel = 'إيصالات زبائن';

    protected static ?string $modelLabel = 'إيصال زبون';

    protected static ?string $pluralModelLabel = 'إيصالات زبائن';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::ReceiptsAndPayments;

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->can('ادخال ايصالات زبائن') || $user?->is_prog;
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation()
            || Auth::user()?->can('تعديل ايصالات زبائن')
            || Auth::user()?->can('الغاء ايصالات زبائن')
            || Auth::user()?->can('االغاء ايصالات زبائن');
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerReceiptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerReceiptsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomerReceipts::route('/'),
            'create' => CreateCustomerReceipt::route('/create'),
            'edit' => EditCustomerReceipt::route('/{record}/edit'),
        ];
    }
}
