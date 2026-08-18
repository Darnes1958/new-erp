<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserForm
{
    public static function configure(Schema $schema): Schema
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
                FileUpload::make('avatar_path')
                    ->label('صورة المستخدم')
                    ->image()
                    ->disk('public')
                    ->directory('user-avatars')
                    ->visibility('public')
                    ->fetchFileInformation(false)
                    ->avatar()
                    ->columnSpanFull(),
                TextInput::make('password')
                    ->label('كلمة المرور')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->visible(fn (string $operation): bool => $operation === 'create'),
                Select::make('company')
                    ->label('الشركة')
                    ->options(fn (): array => \App\Support\CompanyConnections::options())
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
                    ->label('مبرمج')
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
}
