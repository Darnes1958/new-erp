<?php

namespace App\Filament\Admin\Resources\DeductionImportStaging;

use App\Filament\Admin\Resources\DeductionImportStaging\Pages\ListDeductionImportStaging;
use App\Filament\Admin\Resources\DeductionImportStaging\Tables\DeductionImportStagingTable;
use App\Filament\Admin\Resources\DeductionImportStaging\Widgets\DeductionImportDateRangesWidget;
use App\Filament\Admin\Support\ProgrammerAccess;
use App\Models\DeductionImportStagingLine;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DeductionImportStagingResource extends Resource
{
    protected static ?string $model = DeductionImportStagingLine::class;

    protected static ?string $navigationLabel = 'استيراد كشوف الخصم';

    protected static ?string $modelLabel = 'سطر مستورد';

    protected static ?string $pluralModelLabel = 'استيراد من Excel';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|\UnitEnum|null $navigationGroup = 'استيراد Excel';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return ProgrammerAccess::allowed();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return DeductionImportStagingTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeductionImportStaging::route('/'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            DeductionImportDateRangesWidget::class,
        ];
    }
}
