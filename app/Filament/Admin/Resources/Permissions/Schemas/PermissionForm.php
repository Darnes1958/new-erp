<?php

namespace App\Filament\Admin\Resources\Permissions\Schemas;

use App\Models\Permission;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PermissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الصلاحية')->schema([
                TextInput::make('name')
                    ->label('اسم الصلاحية')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('يجب أن يطابق النص المستخدم في فحص الصلاحية داخل المنظومة.'),
                Select::make('for_who')
                    ->label('المجال')
                    ->options(Permission::forWhoOptions())
                    ->default('sell')
                    ->required(),
                Hidden::make('guard_name')
                    ->default('web'),
            ])->columns(2),
        ]);
    }
}
