<?php

namespace App\Filament\Market\Resources\Customers;

use App\Filament\Market\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Market\Resources\Customers\Pages\EditCustomer;
use App\Filament\Market\Resources\Customers\Pages\ListCustomers;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\SalesInvoice;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationLabel = 'زبائن';

    protected static ?string $modelLabel = 'زبون';

    protected static ?string $pluralModelLabel = 'زبائن';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'زبائن وموردين';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الزبون')->schema([
                TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(255),
                Select::make('customer_type_id')
                    ->label('التصنيف')
                    ->relationship('customerType', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255),
                    ]),
                TextInput::make('address')
                    ->label('العنوان')
                    ->maxLength(255),
                TextInput::make('mdar')
                    ->label('مدار')
                    ->maxLength(255),
                TextInput::make('libyana')
                    ->label('لبيانا')
                    ->maxLength(255),
                TextInput::make('card_no')
                    ->label('رقم البطاقة')
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
                TextColumn::make('customerType.name')
                    ->label('التصنيف')
                    ->sortable(),
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
            ->filters([
                SelectFilter::make('customer_type_id')
                    ->label('التصنيف')
                    ->relationship('customerType', 'name'),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->modalHeading('حذف زبون')
                    ->modalDescription('هل أنت متأكد من حذف هذا الزبون؟')
                    ->hidden(fn (Customer $record): bool => SalesInvoice::query()
                        ->where('customer_id', $record->id)
                        ->exists()
                        || CustomerReceipt::query()
                            ->where('customer_id', $record->id)
                            ->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
