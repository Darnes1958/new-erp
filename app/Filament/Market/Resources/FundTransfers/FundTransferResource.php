<?php

namespace App\Filament\Market\Resources\FundTransfers;

use App\Filament\Market\Resources\FundTransfers\Pages\CreateFundTransfer;
use App\Filament\Market\Resources\FundTransfers\Pages\EditFundTransfer;
use App\Filament\Market\Resources\FundTransfers\Pages\ListFundTransfers;
use App\Filament\Market\Resources\FundTransfers\Schemas\FundTransferForm;
use App\Filament\Market\Resources\FundTransfers\Tables\FundTransfersTable;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Filament\Support\AuthorizesErpResource;
use App\Models\FundTransfer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class FundTransferResource extends Resource
{
    use AuthorizesErpResource;

    protected static ?string $model = FundTransfer::class;

    protected static ?string $navigationLabel = 'تحويلات بين الخزائن والمصارف';

    protected static ?string $modelLabel = 'تحويل';

    protected static ?string $pluralModelLabel = 'تحويلات';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::BanksAndCashBoxes;

    protected static ?int $navigationSort = 3;

    protected static function createPermissions(): array
    {
        return ['ادخال تحويل'];
    }

    protected static function deletePermissions(): array
    {
        return ['الغاء تحويل'];
    }

    public static function form(Schema $schema): Schema
    {
        return FundTransferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FundTransfersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFundTransfers::route('/'),
            'create' => CreateFundTransfer::route('/create'),
            'edit' => EditFundTransfer::route('/{record}/edit'),
        ];
    }
}
