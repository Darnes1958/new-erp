<?php

namespace App\Filament\Finance\Resources\RentProfiles;

use App\Filament\Finance\Resources\RentProfiles\Pages\CreateRentProfile;
use App\Filament\Finance\Resources\RentProfiles\Pages\EditRentProfile;
use App\Filament\Finance\Resources\RentProfiles\Pages\ListRentProfiles;
use App\Filament\Finance\Support\FinanceNavigationGroup;
use App\Models\RentProfile;
use App\Models\RentTransaction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RentProfileResource extends Resource
{
    protected static ?string $model = RentProfile::class;

    protected static ?string $navigationLabel = 'إيجارات';

    protected static ?string $modelLabel = 'إيجار';

    protected static ?string $pluralModelLabel = 'إيجارات';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|\UnitEnum|null $navigationGroup = FinanceNavigationGroup::Rents;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('إيجارات');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('الاسم')
                ->required(),
            TextInput::make('rent_amount')
                ->label('الإيجار')
                ->numeric()
                ->required(),
            Select::make('warehouse_id')
                ->label('الصالة أو المحزن')
                ->relationship('warehouse', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Toggle::make('is_active')
                ->label('نشط')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rent_amount')
                    ->label('الإيجار')
                    ->numeric(3)
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('الحالة')
                    ->boolean(),
                TextColumn::make('balance')
                    ->label('الرصيد')
                    ->numeric(3),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (RentProfile $record): bool => RentTransaction::query()
                        ->where('rent_profile_id', $record->id)
                        ->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRentProfiles::route('/'),
            'create' => CreateRentProfile::route('/create'),
            'edit' => EditRentProfile::route('/{record}/edit'),
        ];
    }
}
