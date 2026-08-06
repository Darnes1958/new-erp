<?php

namespace App\Filament\Market\Pages\Reports;

use App\Filament\Ins\Support\InstallmentListPrintActions;
use App\Filament\Market\Pages\Reports\Concerns\InteractsWithMarketReportExports;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\Excel\MarketExcelService;
use App\Services\Market\ItemMovementReportService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ItemMovementReportPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithMarketReportExports;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'حركة صنف';

    protected static ?string $title = 'حركة صنف';

    protected static ?string $slug = 'item-movement-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::WarehousesItems;

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.market.pages.reports.customer-report';

    protected ?string $heading = '';

    public ?int $itemId = null;

    public ?string $itemInput = null;

    public ?string $dateFrom = null;

    public ?int $warehouseId = null;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('تقارير مخازن');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $this->dateFrom = now()->startOfYear()->toDateString();
        $this->filtersForm->fill([
            'itemInput' => $this->itemInput,
            'itemId' => $this->itemId,
            'warehouseId' => $this->warehouseId,
            'dateFrom' => $this->dateFrom,
        ]);
    }

    protected function getForms(): array
    {
        return [
            'filtersForm',
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->schema([
                        TextInput::make('itemInput')
                            ->columnSpan(2)
                            ->label('رقم الصنف')
                            ->numeric()
                            ->live(onBlur: true)
                            ->extraInputAttributes([
                                'wire:keydown.enter' => 'setItemFromInput($event.target.value)',
                            ])
                            ->afterStateUpdated(function (?string $state): void {
                                if (filled($state) && Item::query()->whereKey($state)->exists()) {
                                    $this->itemInput = $state;
                                    $this->itemId = (int) $state;
                                    $this->resetTable();
                                }
                            }),
                        Select::make('itemId')
                            ->columnSpan(2)
                            ->label('بحث')
                            ->options(fn (): array => Item::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (?int $state): void {
                                $this->itemId = $state;
                                $this->itemInput = $state ? (string) $state : null;
                                $this->resetTable();
                            }),
                        Select::make('warehouseId')
                            ->columnSpan(2)
                            ->label('المكان')
                            ->options(fn (): array => Warehouse::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->placeholder('كل الأماكن')
                            ->live()
                            ->afterStateUpdated(function (?int $state): void {
                                $this->warehouseId = $state;
                                $this->resetTable();
                            }),
                        DatePicker::make('dateFrom')
                            ->columnSpan(2)
                            ->label('من تاريخ')
                            ->live()
                            ->afterStateUpdated(function (?string $state): void {
                                $this->dateFrom = $state;
                                $this->resetTable();
                            }),
                        Actions::make([
                            InstallmentListPrintActions::compactExcel('exportExcel', fn () => $this->downloadExcel()),
                        ])
                            ->columnSpan(2)
                            ->extraAttributes(['class' => 'market-compact-exports']),
                    ])
                    ->columns(8),
            ]);
    }

    public function setItemFromInput(mixed $value): void
    {
        if (! filled($value)) {
            return;
        }

        if (Item::query()->whereKey($value)->exists()) {
            $this->itemInput = (string) $value;
            $this->itemId = (int) $value;
            $this->filtersForm->fill([
                'itemInput' => $this->itemInput,
                'itemId' => $this->itemId,
            ]);
            $this->resetTable();
        }
    }

    public function getTableRecordKey(Model|array $record): string
    {
        return (string) $record->row_key;
    }

    public function table(Table $table): Table
    {
        $service = app(ItemMovementReportService::class);

        return $table
            ->query(fn (): Builder => $this->buildReportQuery())
            ->columns([
                TextColumn::make('type')
                    ->label('البيان')
                    ->color(fn (?string $state): ?string => $service->movementTypeColor($state)),
                TextColumn::make('order_date')
                    ->label('تاريخ الفاتورة')
                    ->date(),
                TextColumn::make('id')
                    ->label('رقم الفاتورة')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('العميل')
                    ->wrap(),
                TextColumn::make('price_type')
                    ->label('طريقة الدفع'),
                TextColumn::make('place_name')
                    ->label('المكان')
                    ->wrap(),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->wrap(),
                TextColumn::make('q1')
                    ->label('الكمية')
                    ->numeric(3),
                TextColumn::make('price1')
                    ->label('السعر')
                    ->numeric(3),
                TextColumn::make('sub_tot')
                    ->label('المجموع')
                    ->numeric(3),
            ])
            ->defaultSort(fn ($query) => app(ItemMovementReportService::class)->applyDefaultOrdering($query))
            ->emptyStateHeading($this->itemId ? 'لا توجد بيانات' : 'اختر صنفاً لعرض الحركة')
            ->paginated([10, 25, 50, 100])
            ->striped();
    }

    protected function buildReportQuery(): Builder
    {
        return app(ItemMovementReportService::class)->movementQuery(
            $this->itemId,
            $this->dateFrom,
            $this->warehouseId,
        );
    }

    protected function buildExportQuery(): Builder
    {
        return app(ItemMovementReportService::class)->applyDefaultOrdering(
            $this->buildReportQuery(),
        );
    }

    protected function validateReportFilters(): bool
    {
        if (! filled($this->itemId)) {
            Notification::make()
                ->title('يجب اختيار الصنف')
                ->warning()
                ->send();

            return false;
        }

        if (! filled($this->dateFrom)) {
            Notification::make()
                ->title('يجب اختيار التاريخ')
                ->warning()
                ->send();

            return false;
        }

        return true;
    }

    protected function downloadExcel(): mixed
    {
        $rows = $this->exportRows();

        if ($rows === null) {
            return null;
        }

        $item = Item::query()->find($this->itemId);
        $warehouseName = $this->warehouseId
            ? Warehouse::query()->find($this->warehouseId)?->name
            : null;

        return app(MarketExcelService::class)->itemMovement(
            rows: $rows,
            itemName: (string) ($item?->name ?? ''),
            dateFrom: (string) $this->dateFrom,
            warehouseName: $warehouseName,
        );
    }
}
