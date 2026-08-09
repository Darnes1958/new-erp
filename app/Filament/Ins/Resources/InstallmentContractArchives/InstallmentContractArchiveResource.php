<?php

namespace App\Filament\Ins\Resources\InstallmentContractArchives;

use App\Filament\Ins\Resources\InstallmentContractArchives\Pages\ListInstallmentContractArchives;
use App\Filament\Ins\Resources\InstallmentContractArchives\Pages\ViewInstallmentContractArchive;
use App\Filament\Ins\Resources\InstallmentContractArchives\Schemas\InstallmentContractArchiveInfolist;
use App\Filament\Ins\Resources\InstallmentContractArchives\Tables\InstallmentContractArchivesTable;
use App\Models\InstallmentContractArchive;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InstallmentContractArchiveResource extends Resource
{
    protected static ?string $model = InstallmentContractArchive::class;

    protected static ?string $navigationLabel = 'الأرشيف';

    protected static ?string $modelLabel = 'عقد مؤرشف';

    protected static ?string $pluralModelLabel = 'الأرشيف';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 8;

    public static function getNavigationBadge(): ?string
    {
        $count = InstallmentContractArchive::query()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('تقرير عن عقد من الارشيف');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InstallmentContractArchiveInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstallmentContractArchivesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstallmentContractArchives::route('/'),
            'view' => ViewInstallmentContractArchive::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
