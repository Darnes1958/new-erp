<?php

namespace App\Filament\Market\Resources\Suppliers;

use App\Filament\Market\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Market\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Market\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationLabel = 'موردين';

    protected static ?string $modelLabel = 'مورد';

    protected static ?string $pluralModelLabel = 'موردين';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'زبائن وموردين';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات المورد')->schema([
                TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(255),
                TextInput::make('address')
                    ->label('العنوان')
                    ->maxLength(255),
                TextInput::make('mdar')
                    ->label('مدار')
                    ->maxLength(255),
                TextInput::make('libyana')
                    ->label('لبيانا')
                    ->maxLength(255),
                Hidden::make('created_by')
                    ->default(fn () => Auth::id()),
            ])->columns(2),
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
                TextColumn::make('address')
                    ->label('العنوان')
                    ->toggleable(),
                TextColumn::make('mdar')
                    ->label('مدار')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('libyana')
                    ->label('لبيانا')
                    ->searchable()
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->striped()
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->modalHeading('حذف مورد')
                    ->modalDescription('هل أنت متأكد من حذف هذا المورد؟')
                    ->hidden(fn (Supplier $record): bool => PurchaseInvoice::query()
                        ->where('supplier_id', $record->id)
                        ->exists()
                        || SupplierPayment::query()
                            ->where('supplier_id', $record->id)
                            ->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
