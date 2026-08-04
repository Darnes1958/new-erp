<?php

namespace App\Filament\Admin\Resources\OurCompanies\Schemas;

use App\Support\CompanyConnections;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class OurCompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الشركة')->schema([
                Select::make('connection_name')
                    ->label('اتصال قاعدة البيانات')
                    ->options(fn (): array => CompanyConnections::options())
                    ->searchable()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated()
                    ->visible(fn (): bool => (bool) Auth::user()?->is_prog),
                Hidden::make('connection_name')
                    ->visible(fn (): bool => ! Auth::user()?->is_prog),
                TextInput::make('display_name')
                    ->label('اسم الشركة')
                    ->required()
                    ->maxLength(255),
                TextInput::make('display_name_suffix')
                    ->label('لاحقة الاسم / نشاط الشركة')
                    ->helperText('سطر يشرح عمل الشركة أو اختصاصها، كما في CompanyNameSuffix بالنظام القديم.')
                    ->maxLength(255),
                TextInput::make('comp_code')
                    ->label('رمز الشركة')
                    ->maxLength(32)
                    ->visible(fn (): bool => (bool) Auth::user()?->is_prog),
                TextInput::make('address')
                    ->label('العنوان')
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('phone')
                    ->label('الهاتف')
                    ->tel()
                    ->maxLength(50),
                FileUpload::make('logo_path')
                    ->label('الشعار')
                    ->image()
                    ->disk('public')
                    ->directory('company-logos')
                    ->visibility('public')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('نشطة')
                    ->default(true)
                    ->visible(fn (): bool => (bool) Auth::user()?->is_prog),
            ])->columns(2),
        ]);
    }
}
