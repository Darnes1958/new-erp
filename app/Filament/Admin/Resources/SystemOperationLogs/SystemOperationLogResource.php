<?php

namespace App\Filament\Admin\Resources\SystemOperationLogs;

use App\Filament\Admin\Resources\SystemOperationLogs\Pages\ListSystemOperationLogs;
use App\Filament\Admin\Resources\SystemOperationLogs\Tables\SystemOperationLogsTable;
use App\Filament\Admin\Support\SystemMonitorAccess;
use App\Models\SystemOperationLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SystemOperationLogResource extends Resource
{
    protected static ?string $slug = 'system-operation-logs';

    protected static ?string $model = SystemOperationLog::class;

    protected static ?string $navigationLabel = 'مراقبة التعديل والإلغاء';

    protected static ?string $modelLabel = 'عملية';

    protected static ?string $pluralModelLabel = 'مراقبة العمليات';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'مراقبة النظام';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return SystemMonitorAccess::allowed();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return SystemOperationLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSystemOperationLogs::route('/'),
        ];
    }
}
