<?php

namespace App\Filament\Ins\Pages\Reports;

use App\Models\Customer;
use App\Services\Installments\CustomerInstallmentContractsService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class CustomerContractsReportPage extends Page implements HasForms, HasTable
{
    use HasTabs;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'تقرير عقود الزبون';

    protected static ?string $title = 'تقرير عقود الزبون';

    protected static ?string $slug = 'customer-contracts-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'تقارير';

    protected static ?int $navigationSort = 2;

    protected ?string $heading = '';

    #[Url(as: 'tab')]
    public ?string $activeTab = null;

    public ?int $customerId = null;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog
            || $user?->can('تقرير عن عقد')
            || $user?->can('تقرير عن عقد من الارشيف');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $this->loadDefaultActiveTab();
        $this->refreshFiltersForm();
    }

    protected function getForms(): array
    {
        return ['filtersForm'];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->schema([
                        Select::make('customerId')
                            ->columnSpan(3)
                            ->label('الزبون')
                            ->options(fn (): array => Customer::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (?int $state): void {
                                $this->customerId = $state;
                                $this->resetTable();
                            }),
                    ])
                    ->columns(6),
            ]);
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('قائمة'),
            'archive' => Tab::make('أرشيف')
                ->visible(fn (): bool => Auth::user()?->is_prog || Auth::user()?->can('تقرير عن عقد من الارشيف')),
            'cancelled' => Tab::make('ملغاة'),
            'all' => Tab::make('الكل'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'active';
    }

    public function updatedCustomerId(): void
    {
        $this->resetTable();
        $this->refreshFiltersForm();
    }

    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    public function content(Schema $schema): Schema
    {
        $components = [
            EmbeddedSchema::make('filtersForm'),
        ];

        if (filled($this->customerId)) {
            $components[] = $this->getTabsContentComponent();
            $components[] = EmbeddedTable::make();
        }

        return $schema->components($components);
    }

    public function getTableRecordKey(Model|array $record): string
    {
        if (is_array($record)) {
            return (string) ($record['key'] ?? uniqid());
        }

        return (string) $record->getKey();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (): \Illuminate\Support\Collection {
                if (! filled($this->customerId)) {
                    return collect();
                }

                return app(CustomerInstallmentContractsService::class)
                    ->rowsForCustomer((int) $this->customerId, $this->activeTab ?? 'active');
            })
            ->columns([
                TextColumn::make('id')
                    ->label('رقم العقد')
                    ->sortable(),
                TextColumn::make('status_label')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (array $record): string => match ($record['status'] ?? '') {
                        'archive' => 'gray',
                        'cancelled' => 'danger',
                        default => 'success',
                    })
                    ->visible(fn (): bool => ($this->activeTab ?? 'active') === 'all'),
                TextColumn::make('bank_name')
                    ->label('المصرف'),
                TextColumn::make('bank_account_number')
                    ->label('رقم الحساب')
                    ->toggleable(),
                TextColumn::make('contract_start')
                    ->label('تاريخ العقد'),
                TextColumn::make('contract_total')
                    ->label('قيمة العقد')
                    ->numeric(3),
                TextColumn::make('installment_amount')
                    ->label('القسط')
                    ->numeric(3),
                TextColumn::make('total_paid')
                    ->label('المدفوع')
                    ->numeric(3),
                TextColumn::make('balance')
                    ->label('الباقي')
                    ->numeric(3),
                TextColumn::make('status_date')
                    ->label(fn (): string => match ($this->activeTab ?? 'active') {
                        'archive' => 'تاريخ الأرشفة',
                        'cancelled' => 'تاريخ الإلغاء',
                        default => 'تاريخ الحالة',
                    })
                    ->visible(fn (): bool => in_array($this->activeTab ?? 'active', ['archive', 'cancelled', 'all'], true)),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('عرض')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->url(fn (array $record): string => $record['view_url']),
            ])
            ->emptyStateHeading(fn (): string => filled($this->customerId) ? 'لا توجد عقود' : 'اختر الزبون');
    }

    protected function refreshFiltersForm(): void
    {
        $this->filtersForm->fill([
            'customerId' => $this->customerId,
        ]);
    }
}
