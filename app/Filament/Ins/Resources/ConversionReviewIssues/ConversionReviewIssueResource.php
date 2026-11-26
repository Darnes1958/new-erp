<?php

namespace App\Filament\Ins\Resources\ConversionReviewIssues;

use App\Filament\Ins\Resources\ConversionReviewIssues\Pages\EditConversionReviewIssue;
use App\Filament\Ins\Resources\ConversionReviewIssues\Pages\ListConversionReviewIssues;
use App\Filament\Ins\Resources\ConversionReviewIssues\Schemas\ConversionReviewIssueForm;
use App\Filament\Ins\Resources\ConversionReviewIssues\Tables\ConversionReviewIssuesTable;
use App\Models\ConversionReviewIssue;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ConversionReviewIssueResource extends Resource
{
    protected static ?string $model = ConversionReviewIssue::class;

    protected static ?string $navigationLabel = 'مراجعة بيانات الكونفيرت';

    protected static ?string $modelLabel = 'ملاحظة كونفيرت';

    protected static ?string $pluralModelLabel = 'بيانات تحتاج مراجعة';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'أقساط';

    protected static ?int $navigationSort = 90;

    public static function shouldRegisterNavigation(): bool
    {
        return ConversionReviewIssue::canAccessScreen();
    }

    public static function canAccess(): bool
    {
        return ConversionReviewIssue::canAccessScreen();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! ConversionReviewIssue::canAccessScreen()) {
            return null;
        }

        $open = ConversionReviewIssue::query()->where('status', 'open')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function form(Schema $schema): Schema
    {
        return ConversionReviewIssueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConversionReviewIssuesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversionReviewIssues::route('/'),
            'edit' => EditConversionReviewIssue::route('/{record}/edit'),
        ];
    }
}
