<?php

namespace App\Filament\Admin\Resources\Users;

use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'مستخدمون وصلاحيات';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمون';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'المستخدمون';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && ($user->is_prog || $user->hasRole('admin'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات المستخدم')->schema([
                TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('البريد')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->label('كلمة المرور')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->visible(fn (string $operation): bool => $operation === 'create'),
                Select::make('company')
                    ->label('الشركة')
                    ->options(fn (): array => collect(config('erp.company_connections', []))
                        ->mapWithKeys(fn (string $connection): array => [$connection => $connection])
                        ->all())
                    ->searchable()
                    ->required()
                    ->live()
                    ->visible(fn (): bool => (bool) Auth::user()?->is_prog),
                Select::make('warehouse_id')
                    ->label('مكان العمل (صالة أو المعرض)')
                    ->options(function (Get $get): array {
                        $company = $get('company') ?: Auth::user()?->company;

                        if (! is_string($company) || $company === '') {
                            return [];
                        }

                        if (! config("database.connections.{$company}")) {
                            return [];
                        }

                        return DB::connection($company)
                            ->table('warehouses')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->preload(),
                Select::make('roles')
                    ->label('صلاحيات مجمعة')
                    ->relationship(
                        'roles',
                        'name',
                        fn (Builder $query) => $query
                            ->when(Auth::id() !== 1, fn (Builder $q) => $q->where('name', '!=', 'admin')),
                    )
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Select::make('permissions')
                    ->label('صلاحيات مفردة')
                    ->relationship(
                        'permissions',
                        'name',
                        fn (Builder $query) => $query->where('for_who', 'sell'),
                    )
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Toggle::make('is_prog')
                    ->label('مدير نظام')
                    ->default(false)
                    ->visible(fn (): bool => (bool) Auth::user()?->is_prog),
                Radio::make('status')
                    ->label('الحالة')
                    ->options([
                        1 => 'نشط',
                        0 => 'غير نشط',
                    ])
                    ->inline()
                    ->default(1)
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->where('company', Auth::user()?->company)
                    ->where('is_prog', false);
            })
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('البريد')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label('الأدوار')
                    ->badge(),
                IconColumn::make('status')
                    ->label('الحالة')
                    ->boolean(),
                TextColumn::make('warehouse_id')
                    ->label('المخزن')
                    ->formatStateUsing(function (?int $state, User $record): ?string {
                        if (! $state || ! $record->company) {
                            return null;
                        }

                        return DB::connection($record->company)
                            ->table('warehouses')
                            ->where('id', $state)
                            ->value('name');
                    }),
                TextColumn::make('company')
                    ->label('الشركة')
                    ->visible(fn (): bool => (bool) Auth::user()?->is_prog),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
