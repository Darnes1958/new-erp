<?php

namespace App\Filament\Ins\Resources\DeductionBatches;

use App\Filament\Ins\Resources\DeductionBatches\Pages\CreateDeductionBatch;
use App\Filament\Ins\Resources\DeductionBatches\Pages\EnterDeductionBatchLines;
use App\Filament\Ins\Resources\DeductionBatches\Pages\ListDeductionBatches;
use App\Filament\Ins\Resources\DeductionBatches\Tables\DeductionBatchesTable;
use App\Models\DeductionBatch;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DeductionBatchResource extends Resource
{
    protected static ?string $model = DeductionBatch::class;

    protected static ?string $navigationLabel = 'حوافظ';

    protected static ?string $modelLabel = 'حافظة';

    protected static ?string $pluralModelLabel = 'حوافظ';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'خصومات ومدفوعات';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('ادخال عقود') || $user?->can('تعديل عقود');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return DeductionBatchesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeductionBatches::route('/'),
            'create' => CreateDeductionBatch::route('/create'),
            'enter-lines' => EnterDeductionBatchLines::route('/{record}/enter-lines'),
        ];
    }
}
