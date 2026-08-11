<?php

namespace App\Filament\Admin\Resources\OurCompanies\Schemas;

use App\Support\CompanyConnections;
use App\Support\FilamentSidebarStyle;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                    ->fetchFileInformation(false)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('نشطة')
                    ->default(true)
                    ->visible(fn (): bool => (bool) Auth::user()?->is_prog),
            ])->columns(2),
            Section::make('رسائل الصفحة الرئيسية')
                ->description('تظهر في لوحة التحكم لجميع المستخدمين في هذه الشركة.')
                ->schema([
                    Textarea::make('user_message')
                        ->label('رسالة النظام')
                        ->rows(4)
                        ->columnSpanFull(),
                    Textarea::make('alert_message')
                        ->label('تنبيه مهم')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->visible(fn (): bool => (bool) Auth::user()?->is_prog),
            Section::make('القائمة الجانبية')
                ->description('تباعد عناصر القائمة الجانبية فقط.')
                ->schema([
                    TextInput::make('sidebar_group_gap_px')
                        ->label('المسافة بين المجموعات (px)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(32)
                        ->default(FilamentSidebarStyle::DEFAULT_GROUP_GAP_PX)
                        ->helperText('الافتراضي: 8'),
                    TextInput::make('sidebar_item_gap_px')
                        ->label('المسافة بين عناصر القائمة (px)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(16)
                        ->default(FilamentSidebarStyle::DEFAULT_ITEM_GAP_PX)
                        ->helperText('الافتراضي: 2'),
                    TextInput::make('sidebar_item_padding_y_px')
                        ->label('ارتفاع عنصر القائمة (px)')
                        ->numeric()
                        ->minValue(2)
                        ->maxValue(16)
                        ->default(FilamentSidebarStyle::DEFAULT_ITEM_PADDING_Y_PX)
                        ->helperText('الافتراضي: 4'),
                ])
                ->columns(3)
                ->visible(fn (): bool => (bool) Auth::user()?->is_prog),
            Section::make('الجداول')
                ->description('تباعد وحجم خط الجداول في جميع الشاشات.')
                ->schema([
                    TextInput::make('table_cell_padding_y_px')
                        ->label('ارتفاع صف الجدول (px)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(16)
                        ->default(FilamentSidebarStyle::DEFAULT_TABLE_CELL_PADDING_Y_PX)
                        ->helperText('الافتراضي: 5'),
                    TextInput::make('table_header_padding_y_px')
                        ->label('ارتفاع رأس الجدول (px)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(20)
                        ->default(FilamentSidebarStyle::DEFAULT_TABLE_HEADER_PADDING_Y_PX)
                        ->helperText('الافتراضي: 7'),
                    TextInput::make('table_font_size_px')
                        ->label('حجم خط السطر (px)')
                        ->numeric()
                        ->minValue(10)
                        ->maxValue(18)
                        ->default(FilamentSidebarStyle::DEFAULT_TABLE_FONT_SIZE_PX)
                        ->helperText('الافتراضي: 13'),
                    TextInput::make('table_header_font_size_px')
                        ->label('حجم خط العنوان (px)')
                        ->numeric()
                        ->minValue(10)
                        ->maxValue(18)
                        ->default(FilamentSidebarStyle::DEFAULT_TABLE_HEADER_FONT_SIZE_PX)
                        ->helperText('الافتراضي: 12'),
                ])
                ->columns(2)
                ->visible(fn (): bool => (bool) Auth::user()?->is_prog),
        ]);
    }
}
