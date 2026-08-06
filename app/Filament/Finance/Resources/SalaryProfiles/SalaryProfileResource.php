<?php

namespace App\Filament\Finance\Resources\SalaryProfiles;

use App\Filament\Finance\Resources\SalaryProfiles\Pages\CreateSalaryProfile;
use App\Filament\Finance\Resources\SalaryProfiles\Pages\EditSalaryProfile;
use App\Filament\Finance\Resources\SalaryProfiles\Pages\ListSalaryProfiles;
use App\Filament\Finance\Support\FinanceNavigationGroup;
use App\Models\SalaryProfile;
use App\Models\SalaryTransaction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
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

class SalaryProfileResource extends Resource
{
    protected static ?string $model = SalaryProfile::class;

    protected static ?string $navigationLabel = 'إدراج مرتبات';

    protected static ?string $modelLabel = 'مرتب';

    protected static ?string $pluralModelLabel = 'مرتبات';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = FinanceNavigationGroup::Salaries;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('مرتبات');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('الاسم')
                ->required(),
            TextInput::make('salary_amount')
                ->label('المرتب')
                ->numeric()
                ->required(),
            Select::make('warehouse_id')
                ->label('مكان العمل')
                ->relationship('warehouse', 'name')
                ->searchable()
                ->preload()
                ->placeholder('الإدارة'),
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
                TextColumn::make('salary_amount')
                    ->label('المرتب')
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
                    ->label('إلغاء')
                    ->modalHeading('الغاء المرتب')
                    ->hidden(fn (SalaryProfile $record): bool => SalaryTransaction::query()
                        ->where('salary_profile_id', $record->id)
                        ->exists()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalaryProfiles::route('/'),
            'create' => CreateSalaryProfile::route('/create'),
            'edit' => EditSalaryProfile::route('/{record}/edit'),
        ];
    }
}
