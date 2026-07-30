<?php

namespace App\Filament\Ins\Resources\InstallmentContracts;

use App\Filament\Ins\Resources\InstallmentContracts\Pages\ListInstallmentContracts;
use App\Filament\Ins\Resources\InstallmentContracts\Pages\ViewInstallmentContract;
use App\Models\InstallmentContract;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InstallmentContractResource extends Resource
{
    protected static ?string $model = InstallmentContract::class;

    protected static ?string $navigationLabel = 'عقود التقسيط';

    protected static ?string $modelLabel = 'عقد تقسيط';

    protected static ?string $pluralModelLabel = 'عقود التقسيط';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'عقود التقسيط';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات العقد')->schema([
                TextEntry::make('id')->label('رقم العقد'),
                TextEntry::make('customer.name')->label('الزبون'),
                TextEntry::make('installmentBank.name')->label('البنك'),
                TextEntry::make('workplace.name')->label('جهة العمل'),
                TextEntry::make('bank_account_number')->label('رقم الحساب'),
                TextEntry::make('contract_start')->label('بداية العقد')->date(),
                TextEntry::make('contract_end')->label('نهاية العقد')->date(),
                TextEntry::make('contract_total')->label('قيمة العقد')->numeric(decimalPlaces: 3),
                TextEntry::make('installment_count')->label('عدد الأقساط'),
                TextEntry::make('installment_amount')->label('قيمة القسط')->numeric(decimalPlaces: 3),
                TextEntry::make('total_paid')->label('المدفوع')->numeric(decimalPlaces: 3),
                TextEntry::make('balance')->label('الباقي')->numeric(decimalPlaces: 3),
                TextEntry::make('installments_remaining')->label('أقساط متبقية'),
                TextEntry::make('next_installment_date')->label('قسط قادم')->date(),
                TextEntry::make('late_amount')->label('متأخرات')->numeric(decimalPlaces: 3),
                TextEntry::make('salesInvoice.id')->label('فاتورة البيع'),
                TextEntry::make('notes')->label('ملاحظات')->columnSpanFull(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('الزبون')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('installmentBank.name')
                    ->label('البنك')
                    ->sortable(),
                TextColumn::make('contract_total')
                    ->label('قيمة العقد')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('installment_amount')
                    ->label('القسط')
                    ->numeric(decimalPlaces: 3),
                TextColumn::make('balance')
                    ->label('الباقي')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('installments_remaining')
                    ->label('متبقي')
                    ->sortable(),
                TextColumn::make('next_installment_date')
                    ->label('قسط قادم')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->filters([
                SelectFilter::make('installment_bank_id')
                    ->label('البنك')
                    ->relationship('installmentBank', 'name'),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstallmentContracts::route('/'),
            'view' => ViewInstallmentContract::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
